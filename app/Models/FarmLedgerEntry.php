<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class FarmLedgerEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'farm_id',
        'cycle_id',
        'entry_type',
        'category',
        'amount',
        'entry_date',
        'note',
        'photo_path',
        'source',
        'source_reference_id',
        'entered_by',
        'entered_by_role',
        'voided_at',
        'void_reason',
    ];

    protected $appends = [
        'photo_url',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'entry_date' => 'date:Y-m-d',
            'voided_at' => 'datetime',
        ];
    }

    /**
     * Get the accessible public URL for the receipt image.
     */
    public function getPhotoUrlAttribute(): ?string
    {
        if (! $this->photo_path) {
            return null;
        }

        return Storage::disk('public')->url($this->photo_path);
    }

    /**
     * Alias for entry_type attribute.
     */
    public function getTypeAttribute(): ?string
    {
        return $this->entry_type;
    }

    /**
     * The farm this entry belongs to.
     */
    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    /**
     * The cycle tagged on this entry (optional).
     */
    public function cycle(): BelongsTo
    {
        return $this->belongsTo(FarmCycle::class, 'cycle_id');
    }

    /**
     * The user who recorded this entry.
     */
    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    /**
     * Associated order if system-generated.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'source_reference_id');
    }

    /**
     * Associated service request if system-generated.
     */
    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class, 'source_reference_id');
    }

    /**
     * Active (non-voided) entries scope.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('voided_at');
    }

    /**
     * Voided entries scope.
     */
    public function scopeVoided(Builder $query): Builder
    {
        return $query->whereNotNull('voided_at');
    }

    /**
     * Check if entry is voided.
     */
    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    /**
     * Record an automatic expense entry from an order.
     */
    public static function recordOrderExpense(Order $order): ?self
    {
        if (! $order->farm_id) {
            return null;
        }

        // Avoid duplicate entry if one already exists for this order
        $existing = static::where('source', 'system_order')
            ->where('source_reference_id', $order->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $entryDate = $order->created_at ? $order->created_at->toDateString() : now()->toDateString();

        // Check if an open cycle exists on this farm covering this date
        $cycle = FarmCycle::where('farm_id', $order->farm_id)
            ->where('start_date', '<=', $entryDate)
            ->where(function ($q) use ($entryDate) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', $entryDate);
            })
            ->latest('start_date')
            ->first();

        // Infer user role
        $role = 'farmer';
        $user = $order->user;
        if ($user) {
            if ($user->hasRole('admin')) {
                $role = 'admin';
            } elseif ($user->hasAnyRole(['data_entry_operator', 'deo'])) {
                $role = 'deo';
            }
        }

        // Determine category: derive from order items or use 'Supplies'
        $category = 'Supplies';
        $order->loadMissing('items.product');
        if ($order->items && $order->items->isNotEmpty()) {
            $hasFeed = $order->items->contains(function ($item) {
                $name = strtolower($item->product->name ?? '');
                $cat = strtolower($item->product->category ?? '');
                return str_contains($name, 'feed') || str_contains($cat, 'feed');
            });
            if ($hasFeed) {
                $category = 'Feed';
            }
        }

        return static::create([
            'farm_id' => $order->farm_id,
            'cycle_id' => $cycle?->id,
            'entry_type' => 'expense',
            'category' => $category,
            'amount' => $order->total,
            'entry_date' => $entryDate,
            'note' => "System-generated expense from Order #{$order->invoice_number}",
            'source' => 'system_order',
            'source_reference_id' => $order->id,
            'entered_by' => $order->user_id,
            'entered_by_role' => $role,
        ]);
    }

    /**
     * Record an automatic expense entry from a paid service request (if fee charged).
     */
    public static function recordServiceExpense(ServiceRequest $serviceRequest, float $fee): ?self
    {
        if (! $serviceRequest->farm_id || $fee <= 0) {
            return null;
        }

        $existing = static::where('source', 'system_service')
            ->where('source_reference_id', $serviceRequest->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $entryDate = now()->toDateString();
        $cycle = FarmCycle::where('farm_id', $serviceRequest->farm_id)
            ->where('start_date', '<=', $entryDate)
            ->where(function ($q) use ($entryDate) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', $entryDate);
            })
            ->latest('start_date')
            ->first();

        $category = $serviceRequest->type === 'vet' ? 'Veterinary Care' : 'Consultation Fee';

        return static::create([
            'farm_id' => $serviceRequest->farm_id,
            'cycle_id' => $cycle?->id,
            'entry_type' => 'expense',
            'category' => $category,
            'amount' => $fee,
            'entry_date' => $entryDate,
            'note' => "System-generated visit expense for #SR-{$serviceRequest->id}",
            'source' => 'system_service',
            'source_reference_id' => $serviceRequest->id,
            'entered_by' => $serviceRequest->farmer_id,
            'entered_by_role' => 'farmer',
        ]);
    }
}
