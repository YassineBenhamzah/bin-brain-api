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
        $ext = strtolower($file->getClientOriginalExtension());
        
        $storedFilename = Str::uuid()->toString() . '.pdf';
        $originalFilename = $file->getClientOriginalName();

        if (in_array($ext, ['png', 'jpg', 'jpeg'])) {
            // Normalize filename
            $originalFilename = pathinfo($originalFilename, PATHINFO_FILENAME) . '.pdf';
            
            // Forge into PDF
            $pdf = new \setasign\Fpdf\Fpdf();
            $pdf->AddPage();
            // 210mm width = A4 standard. Proportional height. Force format type because temp file has no extension.
            $pdf->Image($file->path(), 0, 0, 210, 0, strtoupper($ext));
            $pdf->Output('F', '/var/documents/' . $storedFilename);
        } else {
            // It's already a PDF
            $file->move('/var/documents', $storedFilename);
        }

        $document = Document::create([
            'original_filename' => $originalFilename,
            'stored_filename' => $storedFilename,
            'status' => 'pending',
            'user_id' => $request->user()->id,
        ]);
        ProcessDocumentJob::dispatch($document->id);

        return response()->json($document, 201);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $query = Document::with('user:id,name');

        if ($request->has('q')) {
            $q = $request->q;
            $query->where(function($query) use ($q) {
                $query->where('ocr_text', 'LIKE', "%{$q}%")
                      ->orWhere('original_filename', 'LIKE', "%{$q}%")
                      ->orWhereHas('user', function($userQuery) use ($q) {
                          $userQuery->where('name', 'LIKE', "%{$q}%");
                      });
            });
        }

        $documents = $query->latest()->get()->map(function ($doc) {
            $arr = $doc->toArray();
            $arr['handled_by'] = $doc->user ? $doc->user->name : 'Unknown';
            unset($arr['user']);
            return $arr;
        });

        return response()->json($documents);
    }

    public function show($id)
    {
        $document = Document::findOrFail($id);
        return response()->json($document);
    }

    public function download($id)
    {
        $document = Document::find($id);
        
        if (!$document) {
            return response()->json(['error' => 'Document not found in database'], 404);
        }
        $filePath = '/var/documents/' . $document->stored_filename;
        if (!file_exists($filePath)) {
            return response()->json(['error' => 'Physical file missing from server storage'], 404);
        }
        // Securely stream the physical file to the browser with its original name!
        return response()->download($filePath, $document->original_filename);
    }
    public function destroy($id)
    {
        $document = Document::find($id);
        if (!$document) {
            return response()->json(['error' => 'Document not found'], 404);
        }
        // Wipe the physical file from the Docker Volume!
        $filePath = '/var/documents/' . $document->stored_filename;
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        // Erase the record from MySQL database
        $document->delete();
        return response()->json(['message' => 'Document permanently shredded.']);
    }
    public function preview($id)
    {
        $document = Document::find($id);
        if (!$document) return response()->json(['error' => 'Document not found'], 404);
        
        $filePath = '/var/documents/' . $document->stored_filename;
        if (!file_exists($filePath)) return response()->json(['error' => 'Physical file missing'], 404);
        
        // This tells the browser to PREVIEW the file inline instead of downloading it!
        return response()->file($filePath);
    }
}
