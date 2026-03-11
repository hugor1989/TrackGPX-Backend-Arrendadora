
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SW sapien Configuration
    |--------------------------------------------------------------------------
    |
    | Configuración para el servicio de facturación electrónica SW sapien (PAC)
    | Documentación: https://developers.sw.com.mx/
    |
    */

    // URL del servicio de timbrado
    // Pruebas: https://services.test.sw.com.mx
    // Producción: https://services.sw.com.mx
    'url' => env('SW_URL', 'https://services.test.sw.com.mx'),

    // Token de autenticación (infinito o por sesión)
    'token' => env('SW_TOKEN', 'T2lYQ0t4L0RHVkR4dHZ5Nkk1VHNEakZ3Y0J4Nk9GODZuRyt4cE1wVm5tbXB3YVZxTHdOdHAwVXY2NTdJb1hkREtXTzE3dk9pMmdMdkFDR2xFWFVPUXpTUm9mTG1ySXdZbFNja3FRa0RlYURqbzdzdlI2UUx1WGJiKzViUWY2dnZGbFloUDJ6RjhFTGF4M1BySnJ4cHF0YjUvbmRyWWpjTkVLN3ppd3RxL0dJPQ.T2lYQ0t4L0RHVkR4dHZ5Nkk1VHNEakZ3Y0J4Nk9GODZuRyt4cE1wVm5tbFlVcU92YUJTZWlHU3pER1kySnlXRTF4alNUS0ZWcUlVS0NhelhqaXdnWTRncklVSWVvZlFZMWNyUjVxYUFxMWFxcStUL1IzdGpHRTJqdS9Zakw2UGQrNzJ3UWh4TVVxb0g3TU5KV0Q2Um5rb2VpQlZibFk2b3JLeURxQmU5TGhudldsdjExeGpvaDBEQVZYWUhWTE5nKzh5MENnVm9MRjNwRE5MU0xuOWtRdTNGMktEajgrSlVtcVNPbWpLSE9hajJCZC9zOFBEOVp3VG9BbFRaMkFsSHl4ZkoxSWlQYnRERi9kTCtaMkhWeHROSmlUemxHbEhHbDBIMEdueTh0ZmtSOHUwMVNaempVNnlDNTRLRzhxNmU5VlpIdlhJVDMyZ2V2aDVvQzNjRW1YUFVJeXdHcmdvUmhBdVhCS0xyYi9iOUFwbHNlSWN2ZzFTMzVpN2pGTFFUVnRwSXNXZW5zaFcvT2I4VStpMk4zN1dYMnFjS1VPMVRTR0FyTmIzQ05uNUhGTjV3UEJHcE16RDkvWVB5VWEzSmdlTllDSHg2ZXlvd0ZDOUhRb0tDT1l3dHNYV09SeS9qT1p3R0JCZEUyeWViUFZaTFlYM1JXZmRyZG9QQlhOOUY.HK_xei8ivQF6a6lzpTFHfVlFIlPGevzhe7DZK8leDfU'),

    // Credenciales alternativas (si no usas token infinito)
    'user' => env('SW_USER'),
    'password' => env('SW_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | Datos del Emisor (Tu Empresa)
    |--------------------------------------------------------------------------
    */
    'emisor_rfc' => env('SW_EMISOR_RFC'),
    'emisor_nombre' => env('SW_EMISOR_NOMBRE'),
    'emisor_regimen' => env('SW_EMISOR_REGIMEN', '601'), // 601 = General de Ley PM
    'emisor_cp' => env('SW_EMISOR_CP'), // Código postal del emisor

    /*
    |--------------------------------------------------------------------------
    | Configuración de Facturación
    |--------------------------------------------------------------------------
    */
    
    // Serie para facturas
    'serie' => env('SW_SERIE', 'TGX'),
    
    // Clave de producto SAT para servicios de rastreo
    'clave_prod_serv' => env('SW_CLAVE_PROD_SERV', '81161700'),
    
    // Clave de unidad SAT
    'clave_unidad' => env('SW_CLAVE_UNIDAD', 'E48'),

    /*
    |--------------------------------------------------------------------------
    | Regímenes Fiscales (Catálogo SAT)
    |--------------------------------------------------------------------------
    */
    'regimenes_fiscales' => [
        '601' => 'General de Ley Personas Morales',
        '603' => 'Personas Morales con Fines no Lucrativos',
        '605' => 'Sueldos y Salarios e Ingresos Asimilados a Salarios',
        '606' => 'Arrendamiento',
        '607' => 'Régimen de Enajenación o Adquisición de Bienes',
        '608' => 'Demás ingresos',
        '610' => 'Residentes en el Extranjero sin Establecimiento Permanente en México',
        '611' => 'Ingresos por Dividendos (socios y accionistas)',
        '612' => 'Personas Físicas con Actividades Empresariales y Profesionales',
        '614' => 'Ingresos por intereses',
        '615' => 'Régimen de los ingresos por obtención de premios',
        '616' => 'Sin obligaciones fiscales',
        '620' => 'Sociedades Cooperativas de Producción que optan por diferir sus ingresos',
        '621' => 'Incorporación Fiscal',
        '622' => 'Actividades Agrícolas, Ganaderas, Silvícolas y Pesqueras',
        '623' => 'Opcional para Grupos de Sociedades',
        '624' => 'Coordinados',
        '625' => 'Régimen de las Actividades Empresariales con ingresos a través de Plataformas Tecnológicas',
        '626' => 'Régimen Simplificado de Confianza',
    ],

    /*
    |--------------------------------------------------------------------------
    | Usos CFDI (Catálogo SAT)
    |--------------------------------------------------------------------------
    */
    'usos_cfdi' => [
        'G01' => 'Adquisición de mercancías',
        'G02' => 'Devoluciones, descuentos o bonificaciones',
        'G03' => 'Gastos en general',
        'I01' => 'Construcciones',
        'I02' => 'Mobiliario y equipo de oficina por inversiones',
        'I03' => 'Equipo de transporte',
        'I04' => 'Equipo de computo y accesorios',
        'I05' => 'Dados, troqueles, moldes, matrices y herramental',
        'I06' => 'Comunicaciones telefónicas',
        'I07' => 'Comunicaciones satelitales',
        'I08' => 'Otra maquinaria y equipo',
        'D01' => 'Honorarios médicos, dentales y gastos hospitalarios',
        'D02' => 'Gastos médicos por incapacidad o discapacidad',
        'D03' => 'Gastos funerales',
        'D04' => 'Donativos',
        'D05' => 'Intereses reales efectivamente pagados por créditos hipotecarios',
        'D06' => 'Aportaciones voluntarias al SAR',
        'D07' => 'Primas por seguros de gastos médicos',
        'D08' => 'Gastos de transportación escolar obligatoria',
        'D09' => 'Depósitos en cuentas para el ahorro, primas de pensiones',
        'D10' => 'Pagos por servicios educativos (colegiaturas)',
        'S01' => 'Sin efectos fiscales',
        'CP01' => 'Pagos',
        'CN01' => 'Nómina',
    ],

    /*
    |--------------------------------------------------------------------------
    | Formas de Pago (Catálogo SAT)
    |--------------------------------------------------------------------------
    */
    'formas_pago' => [
        '01' => 'Efectivo',
        '02' => 'Cheque nominativo',
        '03' => 'Transferencia electrónica de fondos',
        '04' => 'Tarjeta de crédito',
        '05' => 'Monedero electrónico',
        '06' => 'Dinero electrónico',
        '08' => 'Vales de despensa',
        '12' => 'Dación en pago',
        '13' => 'Pago por subrogación',
        '14' => 'Pago por consignación',
        '15' => 'Condonación',
        '17' => 'Compensación',
        '23' => 'Novación',
        '24' => 'Confusión',
        '25' => 'Remisión de deuda',
        '26' => 'Prescripción o caducidad',
        '27' => 'A satisfacción del acreedor',
        '28' => 'Tarjeta de débito',
        '29' => 'Tarjeta de servicios',
        '30' => 'Aplicación de anticipos',
        '31' => 'Intermediario pagos',
        '99' => 'Por definir',
    ],

    /*
    |--------------------------------------------------------------------------
    | Métodos de Pago (Catálogo SAT)
    |--------------------------------------------------------------------------
    */
    'metodos_pago' => [
        'PUE' => 'Pago en una sola exhibición',
        'PPD' => 'Pago en parcialidades o diferido',
    ],

    /*
    |--------------------------------------------------------------------------
    | Motivos de Cancelación
    |--------------------------------------------------------------------------
    */
    'motivos_cancelacion' => [
        '01' => 'Comprobante emitido con errores con relación',
        '02' => 'Comprobante emitido con errores sin relación',
        '03' => 'No se llevó a cabo la operación',
        '04' => 'Operación nominativa relacionada en factura global',
    ],
];
