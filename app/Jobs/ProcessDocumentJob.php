<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Document;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $documentId;

    public function __construct(int $documentId)
    {
        $this->documentId = $documentId;
    }

    public function handle(): void
    {
        $document = Document::find($this->documentId);
        if (!$document) return;

        // Mark as processing
        $document->update(['status' => 'processing']);
        $filePath = '/var/documents/' . $document->stored_filename;

        try {
            Log::info("Sending document {$document->id} to OCR service");
            
            // Wait up to 300 seconds for a massive PDF to process
            $response = Http::timeout(300)->post('http://ocr:3000/process', [
                'filePath' => $filePath
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $document->update([
                    'ocr_text' => $data['text'],
                    'confidence_score' => $data['confidence'],
                    'status' => 'done'
                ]);
                Log::info("OCR success for document {$document->id}");
            } else {
                $document->update([
                    'status' => 'failed',
                    'failure_reason' => $response->json('error') ?? 'OCR returned an error'
                ]);
            }
        } catch (\Exception $e) {
            Log::error("OCR connection failed: " . $e->getMessage());
            $document->update([
                'status' => 'failed',
                'failure_reason' => 'Could not connect to OCR service'
            ]);
        }
    }
}
