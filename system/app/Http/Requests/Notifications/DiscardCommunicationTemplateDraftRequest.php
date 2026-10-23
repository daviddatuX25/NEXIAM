<?php

declare(strict_types=1);

namespace App\Http\Requests\Notifications;

class DiscardCommunicationTemplateDraftRequest extends CommunicationTemplateRequest
{
    public function rules(): array
    {
        return $this->guards(true);
    }
}
