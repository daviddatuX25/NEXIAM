<?php

namespace App\Policies;

use App\Models\KnowledgeDocument;
use App\Models\User;

class KnowledgeDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('super_admin');
    }

    public function view(User $user, KnowledgeDocument $knowledgeDocument): bool
    {
        return $user->hasRole('super_admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('super_admin');
    }

    public function update(User $user, KnowledgeDocument $knowledgeDocument): bool
    {
        return $user->hasRole('super_admin');
    }

    public function delete(User $user, KnowledgeDocument $knowledgeDocument): bool
    {
        return $user->hasRole('super_admin');
    }
}
