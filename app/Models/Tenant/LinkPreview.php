<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class LinkPreview extends Model
{
    protected $fillable = [
        'url', 'url_hash', 'title', 'description', 'image_url', 'site_name',
        'fetched_at', 'fetch_failed',
    ];

    protected function casts(): array
    {
        return [
            'fetched_at'   => 'datetime',
            'fetch_failed' => 'boolean',
        ];
    }

    /**
     * Shape sent to the client — the raw image URL is never exposed directly,
     * it's always proxied through the authenticated image route so the app
     * never embeds a third-party URL straight into the page. A relative path
     * (matching File::getViewUrlAttribute()'s convention), not route(): these
     * are tenant-subdomain-routed, and the browser is already on the right
     * subdomain, so there's no need to resolve the domain server-side.
     */
    public function toPreviewArray(): array
    {
        return [
            'id'          => $this->id,
            'url'         => $this->url,
            'title'       => $this->title,
            'description' => $this->description,
            'site_name'   => $this->site_name,
            'image_url'   => $this->image_url ? "/link-previews/{$this->id}/image" : null,
        ];
    }
}
