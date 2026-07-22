<?php
declare(strict_types=1);
return [
 'name'=>'Competencia Andina Invernal 2026',
 'timezone'=>getenv('CAI_TIMEZONE')?:'America/Argentina/Buenos_Aires',
 'base_url'=>rtrim(getenv('CAI_BASE_URL')?:'/ea/cai2026','/'),
 'google_client_id'=>getenv('CAI_GOOGLE_CLIENT_ID')?:'',
 'google_client_secret'=>getenv('CAI_GOOGLE_CLIENT_SECRET')?:'',
 'google_redirect_uri'=>getenv('CAI_GOOGLE_REDIRECT_URI')?:''
];

