<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'    => $this->id,
            'slug'  => $this->slug,
            'name'  => $this->name,
            'rfc'   => $this->rfc,
            'fiscal_address' => $this->fiscal_address,
            'contact_email'  => $this->contact_email,
            'phone' => $this->phone,
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }
}
