<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Upload Settings
    |--------------------------------------------------------------------------
    | Tamanho máximo de arquivo (em MB) aceito nas APIs do Nodal.
    | Utilizado no UploadResourceRequest para validação no nível da aplicação,
    | independente do php.ini do servidor.
    |
    | O Google Drive suporta multipart upload para arquivos até 5 MB e
    | resumable upload para arquivos maiores. O limite abaixo é para a
    | primeira versão da API de upload (multipart).
    |
    | Para alterar, mude aqui. Nunca coloque número mágico no Service/Request.
    */
    'max_upload_size_mb' => env('NODAL_MAX_UPLOAD_SIZE_MB', 50),

];
