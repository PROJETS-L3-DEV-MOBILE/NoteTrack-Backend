<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Compte de l'utilisateur courant.
 *
 * Ces trois routes ciblent toujours le porteur du token : aucun id n'est
 * accepté en paramètre, il n'y a donc rien à autoriser au-delà du rôle
 * (cf. middleware isAdmin sur PATCH / DELETE).
 */
class UserController extends Controller
{
    /**
     * GET /user
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json($this->withProfile($request->user()), 200);
    }

    /**
     * PATCH /user
     *
     * Renvoie le même contrat que GET /user : le client réutilise la réponse
     * pour rafraîchir son cache utilisateur.
     */
    public function update(UpdateUserRequest $request): JsonResponse
    {
        $user = $request->user();

        // Le username vit sur la table de profil (admins.username), pas sur users.
        $user->admin->update([
            'username' => $request->validated('username'),
        ]);

        return response()->json($this->withProfile($user->refresh()), 200);
    }

    /**
     * DELETE /user
     *
     * Soft delete plutôt que suppression physique : des enseignants et des
     * matières référencent l'admin via admin_id / added_by.
     */
    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($this->isLastActiveAdmin($user)) {
            return response()->json([
                'message' => 'Impossible de supprimer le dernier compte administrateur actif.',
            ], 403);
        }

        DB::transaction(function () use ($user) {
            // Révoque access_token et refresh_token : plus aucun appel possible.
            $user->tokens()->delete();

            $user->is_deleted = true;
            $user->save();

            $user->delete();
        });

        return response()->json([
            'message' => 'Votre compte a bien été supprimé.',
        ], 200);
    }

    /**
     * Sérialisation partagée par GET /user et PATCH /user.
     */
    private function withProfile(User $user): User
    {
        $user->append('profile');

        return $user;
    }

    private function isLastActiveAdmin(User $user): bool
    {
        return User::query()
            ->where('role', 'admin')
            ->where('is_deleted', false)
            ->whereKeyNot($user->getKey())
            ->doesntExist();
    }
}
