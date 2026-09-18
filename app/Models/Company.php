<?php

namespace App\Models;

use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string|null $domain
 * @property string|null $industry
 * @property string|null $size
 * @property array<string, mixed>|null $enrichment_data
 * @property Carbon|null $enriched_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'domain', 'industry', 'size', 'enrichment_data', 'enriched_at'])]
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enrichment_data' => 'array',
            'enriched_at' => 'datetime',
        ];
    }

    /**
     * Normalize a domain or website URL to a bare host, e.g. "https://www.Acme.com/about" becomes "acme.com".
     */
    public static function normalizeDomain(string $domain): ?string
    {
        $domain = Str::lower(trim($domain));

        if ($domain === '') {
            return null;
        }

        $host = parse_url(str_contains($domain, '://') ? $domain : "http://{$domain}", PHP_URL_HOST);

        return is_string($host) ? Str::chopStart($host, 'www.') : $domain;
    }

    /**
     * @return HasMany<Contact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    /**
     * Scope the query to companies whose name or domain contains the term.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function search(Builder $query, string $term): void
    {
        $query->whereAny(['name', 'domain'], 'like', "%{$term}%");
    }
}
