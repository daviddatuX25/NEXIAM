<?php

declare(strict_types=1);

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Notifications\Templates\Queries\CommunicationTemplateEditorQuery;
use App\Notifications\Templates\Queries\NoticeMessageComposerQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ExaminationCommunicationTemplateController extends Controller
{
    public function studio(Request $request, string $slug, CommunicationTemplateEditorQuery $query, NoticeMessageComposerQuery $messages): Response
    {
        $metadata = $query->family($request->user(), 'examination', $slug);
        if (NoticeMessageComposerQuery::supports('examination', $slug) && $metadata['can_edit']) {
            return Inertia::render('Setup/Notifications/Templates/Message', $messages->editor(
                $request->user(), 'examination', $slug, $request->query('version'), $request->query('source_version'),
            ));
        }

        return Inertia::render('Setup/Notifications/Templates/Studio', NoticeMessageComposerQuery::supports('examination', $slug)
            ? $metadata
            : $query->family($request->user(), 'examination', $slug, $request->query('version'), $request->query('inspect')));
    }
}
