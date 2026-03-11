<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    protected $table = 'platform_settings';

    protected $fillable = [
        'name',
        'rfc',
        'fiscal_address',
        'contact_email',
        'contact_phone',
        'base_currency',
        'default_language',
        'timezone',
        'logo_url',
        'invoice_series',
        'invoice_cert_number'
    ];
}
