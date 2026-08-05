<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Note\StoreNoteRequest;
use App\Http\Requests\Note\UpdateNoteRequest;
use App\Http\Resources\NoteResource;
use App\Models\Note;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class NoteController extends Controller
{
    private const MAX_IMAGES_PER_NOTE = 3;

    public function index(Request $request): JsonResponse
    {
        $notes = $request->user()
            ->notes()
            ->with(['images', 'checklists', 'folder'])
            ->when($request->filled('folder_id'), fn ($q) => $q->where('folder_id', $request->string('folder_id')))
            ->latest('updated_at')
            ->get();

        $totalAllNotes = $notes->count();
        return response()->json([
            'message' => 'Daftar note berhasil diambil.',
            'data' => [
                'total_all_notes' => $totalAllNotes, 
                'notes' => NoteResource::collection($notes)
                ],
        ]);
    }

    public function store(StoreNoteRequest $request): JsonResponse
    {
        $userId = $request->user()->id;

        $note = DB::transaction(function () use ($request, $userId) {
            $note = Note::create([
                'user_id' => $userId,
                'folder_id' => $request->validated('folder_id'),
                'title' => $request->validated('title'),
                'content' => $request->validated('content'),
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            foreach ($request->file('images', []) as $file) {
                $path = $file->store("notes/{$note->id}", 'local');
                $note->images()->create([
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'mime_type' => $file->getClientMimeType(),
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);
            }

            foreach ($request->validated('checklists', []) as $position => $item) {
                $note->checklists()->create([
                    'content' => $item['content'],
                    'is_completed' => $this->toBoolean($item['is_completed'] ?? false),
                    'position' => $position + 1,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);
            }

            return $note;
        });

        $note->load(['images', 'checklists', 'folder']);

        return response()->json([
            'message' => 'Note berhasil dibuat.',
            'data' => new NoteResource($note),
        ], 201);
    }

    public function show(Note $note): JsonResponse
    {
        $note->load(['images', 'checklists', 'folder']);
        return response()->json([
            'message' => 'Detail note berhasil diambil.',
            'data' => new NoteResource($note),
        ]);
    }

    public function update(UpdateNoteRequest $request, Note $note): JsonResponse
    {
        $userId = $request->user()->id;

        DB::transaction(function () use ($request, $note, $userId) {
            $note->update([
                ...$request->safe()->only(['title', 'content', 'folder_id']),
                'updated_by' => $userId,
            ]);

            $this->syncImages($request, $note, $userId);
            $this->syncChecklists($request, $note, $userId);

            $note->touch();
        });

        $note->load(['images', 'checklists', 'folder']);

        return response()->json([
            'message' => 'Note berhasil diperbarui.',
            'data' => new NoteResource($note),
        ]);
    }

    private function syncImages(UpdateNoteRequest $request, Note $note, string $userId): void
    {
        if ($request->boolean('existing_image_ids_provided')) {
            $keepIds = $request->input('existing_image_ids', []);

            $note->images()->whereNotIn('id', $keepIds)->get()->each(function ($image) use ($userId) {
                Storage::disk('local')->delete($image->file_path);
                $image->update(['deleted_by' => $userId]);
                $image->delete();
            });
        }

        $newFiles = $request->file('images', []);

        if ($newFiles && $note->images()->count() + count($newFiles) > self::MAX_IMAGES_PER_NOTE) {
            abort(422, 'Maksimal ' . self::MAX_IMAGES_PER_NOTE . ' gambar per note.');
        }

        foreach ($newFiles as $file) {
            $path = $file->store("notes/{$note->id}", 'local');
            $note->images()->create([
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        }
    }

    private function syncChecklists(UpdateNoteRequest $request, Note $note, string $userId): void
    {
        if (! $request->boolean('checklists_provided')) {
            return;
        }

        $checklists = $request->validated('checklists', []);
        $keepIds = collect($checklists)->pluck('id')->filter()->all();

        $note->checklists()->whereNotIn('id', $keepIds)->get()->each(function ($item) use ($userId) {
            $item->update(['deleted_by' => $userId]);
            $item->delete();
        });

        foreach ($checklists as $position => $item) {
            if (! empty($item['id'])) {
                $note->checklists()->where('id', $item['id'])->update([
                    'content' => $item['content'],
                    'is_completed' => $this->toBoolean($item['is_completed'] ?? false),
                    'position' => $position + 1,
                    'updated_by' => $userId,
                ]);
            } else {
                $note->checklists()->create([
                    'content' => $item['content'],
                    'is_completed' => $this->toBoolean($item['is_completed'] ?? false),
                    'position' => $position + 1,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);
            }
        }
    }

    private function toBoolean(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function destroy(Request $request, Note $note): JsonResponse
    {
        $note->update(['deleted_by' => $request->user()->id]);
        $note->delete();

        return response()->json([
            'message' => 'Note berhasil dihapus.',
            'data' => []
        ]);
    }
}