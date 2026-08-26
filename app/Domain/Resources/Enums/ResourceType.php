<?php

namespace App\Domain\Resources\Enums;

enum ResourceType: string
{
    case FOLDER = 'folder';
    case SHARED_DRIVE = 'shared_drive';
    case DOCUMENT = 'document';
    case SPREADSHEET = 'spreadsheet';
    case PRESENTATION = 'presentation';
    case PDF = 'pdf';
    case IMAGE = 'image';
    case VIDEO = 'video';
    case AUDIO = 'audio';
    case CALENDAR = 'calendar';
    case FORM = 'form';
    case DRAWING = 'drawing';
    case SHORTCUT = 'shortcut';
    case AD_ACCOUNT = 'ad_account';
    case FACEBOOK_PAGE = 'facebook_page';
    case INSTAGRAM_ACCOUNT = 'instagram_account';
    case CAMPAIGN = 'campaign';
    case AD_SET = 'ad_set';
    case AD = 'ad';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::FOLDER => 'Pasta',
            self::SHARED_DRIVE => 'Drive Compartilhado',
            self::DOCUMENT => 'Documento',
            self::SPREADSHEET => 'Planilha',
            self::PRESENTATION => 'Apresentação',
            self::PDF => 'PDF',
            self::IMAGE => 'Imagem',
            self::VIDEO => 'Vídeo',
            self::AUDIO => 'Áudio',
            self::CALENDAR => 'Calendário',
            self::FORM => 'Formulário',
            self::DRAWING => 'Desenho',
            self::SHORTCUT          => 'Atalho',
            self::AD_ACCOUNT        => 'Conta de Anúncio',
            self::FACEBOOK_PAGE     => 'Página do Facebook',
            self::INSTAGRAM_ACCOUNT => 'Conta do Instagram',
            self::CAMPAIGN          => 'Campanha',
            self::AD_SET            => 'Conjunto de Anúncios',
            self::AD                => 'Anúncio',
            self::OTHER             => 'Outro',
        };
    }
}
