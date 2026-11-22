<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Host;
use App\Models\LiveSession;
use App\Models\Worker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Sheet "Jam Live" + data host di sheet "Daftar". */
class HostController extends Controller
{
    public function sessions(Request $request): View
    {
        $sessions = LiveSession::query()->with('host')
            ->when($request->input('host'), fn ($query, $host) => $query->where('host_id', $host))
            ->when($request->input('month'), fn ($query, $month) => $query->where('date', 'like', "{$month}%"))
            ->latest('date')->latest('id')
            ->paginate(30)->withQueryString();

        return view('admin.hosts.sessions', ['sessions' => $sessions, 'hosts' => Host::orderBy('name')->get()]);
    }

    public function createSession(): View
    {
        return view('admin.hosts.session-form', ['session' => new LiveSession(['date' => now()]), 'hosts' => Host::where('is_active', true)->orderBy('name')->get()]);
    }

    public function storeSession(Request $request): RedirectResponse
    {
        LiveSession::create($this->validatedSession($request));

        return redirect()->route('admin.live-sessions.index')->with('status', 'Sesi live dicatat.');
    }

    public function editSession(LiveSession $session): View
    {
        return view('admin.hosts.session-form', ['session' => $session, 'hosts' => Host::orderBy('name')->get()]);
    }

    public function updateSession(Request $request, LiveSession $session): RedirectResponse
    {
        $session->update($this->validatedSession($request));

        return redirect()->route('admin.live-sessions.index')->with('status', 'Sesi live diperbarui.');
    }

    public function destroySession(LiveSession $session): RedirectResponse
    {
        $session->delete();

        return back()->with('status', 'Sesi live dihapus.');
    }

    public function index(): View
    {
        return view('admin.hosts.index', [
            'hosts' => Host::withCount('sessions')->orderBy('name')->get(),
            'workers' => Worker::orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Host::create($this->validatedHost($request));

        return back()->with('status', 'Host ditambahkan.');
    }

    public function update(Request $request, Host $host): RedirectResponse
    {
        $host->update($this->validatedHost($request, $host));

        return back()->with('status', "Data host {$host->name} disimpan.");
    }

    public function destroy(Host $host): RedirectResponse
    {
        if ($host->sessions()->exists() || $host->payrolls()->exists()) {
            return back()->with('error', 'Host sudah punya jam live atau rekap gaji. Nonaktifkan saja.');
        }

        $host->delete();

        return back()->with('status', 'Host dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedSession(Request $request): array
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'host_id' => ['required', 'exists:hosts,id'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'manual_hours' => ['nullable', 'numeric', 'min:0'],
            'store_account' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        if (($data['manual_hours'] ?? null) === null && (empty($data['start_time']) || empty($data['end_time']))) {
            throw ValidationException::withMessages(['start_time' => 'Isi jam mulai & selesai, atau isi jam manual.']);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedHost(Request $request, ?Host $host = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('hosts', 'name')->ignore($host)],
            'hourly_rate' => ['required', 'numeric', 'min:0'],
            'account' => ['nullable', 'string', 'max:255'],
        ]);

        return [...$data, 'is_active' => $request->boolean('is_active', true)];
    }
}
