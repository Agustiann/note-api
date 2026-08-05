<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Note;
use App\Models\NoteImage;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class NoteImageController extends Controller
{

    public function show(Note $note, NoteImage $image): BinaryFileResponse
    {
        abort_unless($image->note_id === $note->id, 404, 'Gambar tidak ditemukan pada note ini.');
        $path = Storage::disk('local')->path($image->file_path);
        abort_unless(file_exists($path), 404, 'File gambar tidak ditemukan.');
        return response()->file($path, [
            'Content-Type' => $image->mime_type,
        ]);
    }
}
