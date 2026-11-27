<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\StoresSiteUploads;
use App\Http\Controllers\Controller;
use App\Models\DesignSimulation;
use App\Services\DesignAi;
use App\Services\Tripo3d;
use App\Support\ExhaustSimulator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Simulasi Desain: desain knalpot 3D parametrik (leheran + silincer) dengan
 * foto referensi, mode realistis & CAD, dan estimasi tambahan tenaga.
 */
class DesignSimulationController extends Controller
{
    use StoresSiteUploads;

    private const MAX_REFERENCES = 8;

    private const MAX_MOTOR_PHOTOS = 6;

    public function index(): View
    {
        return view('admin.design-sims.index', [
            'designs' => DesignSimulation::query()->latest('updated_at')->get(),
            'motors' => ExhaustSimulator::MOTORS,
            'styles' => ExhaustSimulator::STYLES,
        ]);
    }

    public function create(Request $request): View
    {
        $motor = array_key_exists((string) $request->query('motor'), ExhaustSimulator::MOTORS) ? (string) $request->query('motor') : 'mio';
        $style = array_key_exists((string) $request->query('gaya'), ExhaustSimulator::STYLES) ? (string) $request->query('gaya') : 'gp';

        return $this->editor(new DesignSimulation([
            'name' => 'Knalpot racing '.ExhaustSimulator::MOTORS[$motor]['label'],
            'motor' => $motor,
            'params' => ExhaustSimulator::autoDesign($motor, $style),
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $design = new DesignSimulation(['user_id' => $request->user()->id]);
        $this->fill($design, $request);

        return redirect()->route('admin.design-sims.edit', $design)->with('status', 'Desain '.$design->name.' disimpan.');
    }

    public function edit(DesignSimulation $designSim): View
    {
        return $this->editor($designSim);
    }

    public function update(Request $request, DesignSimulation $designSim): RedirectResponse
    {
        $this->fill($designSim, $request);

        return redirect()->route('admin.design-sims.edit', $designSim)->with('status', 'Desain '.$designSim->name.' disimpan.');
    }

    public function destroy(DesignSimulation $designSim): RedirectResponse
    {
        $models = array_filter([$designSim->params['models']['exhaust'] ?? null, $designSim->params['models']['motor'] ?? null]);
        foreach ([...($designSim->references ?? []), ...($designSim->motor_photos ?? []), ...($designSim->renders ?? []), ...$models, $designSim->thumbnail] as $path) {
            $this->deleteSiteUpload($path);
        }
        $designSim->delete();

        return redirect()->route('admin.design-sims.index')->with('status', 'Desain '.$designSim->name.' dihapus.');
    }

    public function duplicate(DesignSimulation $designSim): RedirectResponse
    {
        $copy = $designSim->replicate(['references', 'motor_photos', 'renders', 'thumbnail']);
        $copy->name = Str::limit($designSim->name, 240, '').' (salinan)';
        $copy->user_id = auth()->id();
        $copy->save();

        return redirect()->route('admin.design-sims.edit', $copy)->with('status', 'Desain disalin. Foto referensi tidak ikut disalin.');
    }

    /** Hitung ulang estimasi saat ukuran diubah di editor (JSON). */
    public function simulate(Request $request): JsonResponse
    {
        return response()->json(ExhaustSimulator::simulate((array) $request->input('params', [])));
    }

    /** "Buat desain otomatis": ukuran awal yang pas untuk motor + gaya (JSON). */
    public function auto(Request $request): JsonResponse
    {
        $data = $request->validate([
            'motor' => ['required', Rule::in(array_keys(ExhaustSimulator::MOTORS))],
            'style' => ['required', Rule::in(array_keys(ExhaustSimulator::STYLES))],
            'target_rpm' => ['nullable', 'integer', 'between:4000,14000'],
            'custom_cc' => ['nullable', 'numeric', 'between:50,1000'],
            'custom_hp' => ['nullable', 'numeric', 'between:2,200'],
            'custom_rpm' => ['nullable', 'integer', 'between:4000,14000'],
        ]);
        $custom = array_filter(['custom_cc' => $data['custom_cc'] ?? null, 'custom_hp' => $data['custom_hp'] ?? null, 'custom_rpm' => $data['custom_rpm'] ?? null], fn ($v) => $v !== null);
        $params = ExhaustSimulator::autoDesign($data['motor'], $data['style'], $data['target_rpm'] ?? null, $custom);

        return response()->json(['params' => $params, 'result' => ExhaustSimulator::simulate($params)]);
    }

    private function editor(DesignSimulation $design): View
    {
        $params = ExhaustSimulator::normalize($design->params ?? []);

        return view('admin.design-sims.editor', [
            'design' => $design,
            'params' => $params,
            'result' => ExhaustSimulator::simulate($params),
            'motors' => ExhaustSimulator::MOTORS,
            'styles' => ExhaustSimulator::STYLES,
            'tips' => ExhaustSimulator::TIPS,
            'finishes' => ExhaustSimulator::FINISHES,
            'model3dReady' => app(Tripo3d::class)->enabled(),
            'aiReady' => app(DesignAi::class)->enabled(),
            'motorPhotos' => $design->motorPhotoUrls(),
        ]);
    }

    private function fill(DesignSimulation $design, Request $request): void
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'params' => ['required', 'json'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'references' => ['array'],
            'references.*' => ['image', 'max:8192'],
            'remove_references' => ['array'],
            'remove_references.*' => ['string'],
            'thumbnail' => ['nullable', 'string', 'max:3000000'],
            'motor_photos' => ['array'],
            'motor_photos.*' => ['image', 'max:10240'],
            'remove_motor_photos' => ['array'],
            'remove_motor_photos.*' => ['string'],
        ]);

        $params = ExhaustSimulator::normalize((array) json_decode($data['params'], true));
        $references = array_values(array_diff($design->references ?? [], $data['remove_references'] ?? []));
        foreach (array_intersect($design->references ?? [], $data['remove_references'] ?? []) as $removed) {
            $this->deleteSiteUpload($removed);
        }

        $uploads = $request->file('references', []);
        if (count($references) + count($uploads) > self::MAX_REFERENCES) {
            throw ValidationException::withMessages(['references' => 'Maksimal '.self::MAX_REFERENCES.' foto referensi per desain.']);
        }
        foreach ($uploads as $file) {
            $references[] = $this->storeSiteUpload($file, 'desain');
        }

        $photos = $this->syncPaths($design->motor_photos ?? [], $data['remove_motor_photos'] ?? [], $request->file('motor_photos', []), 'desain/motor', self::MAX_MOTOR_PHOTOS, 'motor_photos', 'foto motor');
        $params['mockup']['photo'] = min($params['mockup']['photo'], max(count($photos) - 1, 0));
        if ($params['source']['type'] === 'reference' && ! isset($references[$params['source']['index']])) {
            $params['source'] = ['type' => 'parametric'];   // foto referensinya sudah dihapus
        }

        $design->fill([
            'name' => $data['name'],
            'motor' => $params['motor'],
            'params' => $params,
            'references' => $references,
            'motor_photos' => $photos,
            'notes' => $data['notes'] ?? null,
        ]);

        if ($thumbnail = $this->storeThumbnail($data['thumbnail'] ?? null)) {
            $this->deleteSiteUpload($design->thumbnail);
            $design->thumbnail = $thumbnail;
        }

        $design->save();
    }

    /**
     * Daftar file setelah dihapus/ditambah (hanya path milik desain ini yang boleh dihapus).
     *
     * @param  list<string>  $current
     * @param  list<string>  $remove
     * @param  array<int, UploadedFile>  $uploads
     * @return list<string>
     */
    private function syncPaths(array $current, array $remove, array $uploads, string $directory, int $max, string $field, string $label): array
    {
        $kept = array_values(array_diff($current, $remove));
        if (count($kept) + count($uploads) > $max) {
            throw ValidationException::withMessages([$field => 'Maksimal '.$max.' '.$label.' per desain.']);
        }
        foreach (array_intersect($current, $remove) as $removed) {
            $this->deleteSiteUpload($removed);
        }
        foreach ($uploads as $file) {
            $kept[] = $this->storeSiteUpload($file, $directory);
        }

        return $kept;
    }

    /** Gambar pratinjau 3D dari kanvas (data URL PNG/JPEG) → file di uploads/desain. */
    private function storeThumbnail(?string $dataUrl): ?string
    {
        if (! $dataUrl || ! preg_match('#^data:image/(png|jpeg);base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $match)) {
            return null;
        }

        $binary = base64_decode($match[2], true);
        if ($binary === false || strlen($binary) > 2_000_000 || @getimagesizefromstring($binary) === false) {
            return null;
        }

        $path = 'uploads/desain/pratinjau-'.Str::random(24).'.'.($match[1] === 'png' ? 'png' : 'jpg');
        Storage::disk('site')->put($path, $binary);

        return $path;
    }
}
