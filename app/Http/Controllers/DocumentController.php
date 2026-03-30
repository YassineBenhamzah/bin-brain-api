<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Document;
use Illuminate\Support\Str;
use App\Jobs\ProcessDocumentJob;

class DocumentController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'pdf' => 'required|mimes:pdf,png,jpg,jpeg|max:10240',
        ]);

        $file = $request->file('pdf');
        $storedFilename = Str::uuid()->toString() . '.' . $file->getClientOriginalExtension();

        $file->move('/var/documents', $storedFilename);

        $document = Document::create([
            'original_filename' => $file->getClientOriginalName(),
            'stored_filename' => $storedFilename,
            'status' => 'pending',
        ]);
        ProcessDocumentJob::dispatch($document->id);

        return response()->json($document, 201);
    }

    public function index(Request $request)
    {
        $query = Document::query();

        if ($request->has('q')) {
            $query->where('ocr_text', 'LIKE', '%' . $request->q . '%');
        }

        return response()->json($query->latest()->get());
    }

    public function show($id)
    {
        $document = Document::findOrFail($id);
        return response()->json($document);
    }
}
