<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\StoresSiteUploads;
use App\Http\Controllers\Controller;
use App\Models\ContentBlock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Teks & gambar halaman website yang bisa diedit (content('key', default)). */
class ContentBlockController extends Controller
{
    use StoresSiteUploads;

    public function index(Request $request): View
    {
        $blocks = ContentBlock::query()
            ->when($request->input('page'), fn ($query, $page) => $query->where('page', $page))
            ->when($request->input('type'), fn ($query, $type) => $query->where('type', $type))
            ->when($request->string('q')->trim()->value(), fn ($query, $search) => $query->where(
                fn ($query) => $query->where('label', 'like', "%{$search}%")
                    ->orWhere('value', 'like', "%{$search}%")
                    ->orWhere('key', 'like', "%{$search}%")
            ))
            ->orderBy('page')->orderBy('sort_order')->orderBy('id')
            ->paginate(30)->withQueryString();

        return view('admin.website.content.index', ['blocks' => $blocks, 'pages' => ContentBlock::PAGES]);
    }

    public function edit(ContentBlock $contentBlock): View
    {
        return view('admin.website.content.edit', ['block' => $contentBlock, 'pages' => ContentBlock::PAGES]);
    }

    public function update(Request $request, ContentBlock $contentBlock): RedirectResponse
    {
        if ($contentBlock->type === 'image') {
            $data = $request->validate([
                'label' => ['required', 'string', 'max:255'],
                'image' => ['nullable', 'image', 'max:4096'],
            ]);

            if ($request->hasFile('image')) {
                $this->deleteSiteUpload($contentBlock->value);
                $contentBlock->value = $this->storeSiteUpload($request->file('image'), 'content');
            }
        } else {
            $data = $request->validate([
                'label' => ['required', 'string', 'max:255'],
                'value' => ['nullable', 'string'],
            ]);
            $contentBlock->value = $data['value'] ?? null;
        }

        $contentBlock->label = $data['label'];
        $contentBlock->save();

        return redirect()->route('admin.content-blocks.index', $request->only('page'))
            ->with('status', "Konten \"{$contentBlock->label}\" disimpan.");
    }
}
