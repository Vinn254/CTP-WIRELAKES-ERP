<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Module 8 §11.4 step 2 — one thing the inspector was asked to check, and what they found. A fail is not deleted to
 * make a report pass: it is answered by the worker's proof and the inspector's dated re-check on the same line.
 */
class SiteInspectionItem extends Model
{
    public const RESULTS = [
        'pending' => 'Not checked yet',
        'pass' => 'Passed',
        'fail' => 'Failed — needs rework',
        'n_a' => 'Not applicable on this site',
    ];

    protected $fillable = [
        'inspection_id', 'item', 'result', 'note',
        'proof_path', 'proof_name', 'proof_size', 'proof_note', 'proof_on', 'proved_by_fundi', 'rechecked_on',
    ];

    protected $casts = ['proof_on' => 'datetime', 'rechecked_on' => 'date'];

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(SiteInspection::class, 'inspection_id');
    }

    public function prover(): BelongsTo
    {
        return $this->belongsTo(FundiRegistration::class, 'proved_by_fundi')->withDefault();
    }

    public function resultLabel(): string
    {
        return self::RESULTS[$this->result] ?? ucfirst(str_replace('_', ' ', (string) $this->result));
    }

    public function hasProof(): bool
    {
        return filled($this->proof_path);
    }

    /** A fail is cleared by a re-check that passes it — never by deleting the line. */
    public function isCleared(): bool
    {
        return $this->result !== 'fail' || $this->rechecked_on !== null;
    }

    public function storeProof(object $file, string $note, ?int $fundiId): void
    {
        $this->update([
            'proof_path' => $file->store('construction/inspections/'.$this->inspection_id, 'local'),
            'proof_name' => $file->getClientOriginalName(),
            'proof_size' => $file->getSize(),
            'proof_note' => str($note)->limit(1000, '')->toString(),
            'proof_on' => now(),
            'proved_by_fundi' => $fundiId,
        ]);
    }
}
