<?php

namespace App\Http\Controllers\Oauth;

use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Passport\Bridge\Scope;
use Laravel\Passport\Http\Controllers\ApproveAuthorizationController as PassportApproveController;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Passport's approve step, plus the one way a connection can carry moderator
 * powers: the moderator ticked "Include moderator powers for this
 * connection" on the consent screen. The client cannot ask for the scope
 * (EnsureMcpConsentAllowed refuses it); only the signed-in human can add it,
 * per connection, and the account has to be a moderator on this web session
 * — isModerator() here is the plain role check, no token being involved.
 */
class ApproveAuthorizationController extends PassportApproveController
{
    public function approve(Request $request, ResponseInterface $psrResponse): Response
    {
        $authRequest = $this->getAuthRequestFromSession($request);
        $authRequest->setAuthorizationApproved(true);

        if ($request->boolean('moderate') && $request->user()?->isModerator()) {
            $scopes = $authRequest->getScopes();
            $ids = array_map(fn (ScopeEntityInterface $scope) => $scope->getIdentifier(), $scopes);

            if (! in_array(User::MODERATE_SCOPE, $ids, true)) {
                $scopes[] = new Scope(User::MODERATE_SCOPE);
            }

            $authRequest->setScopes($scopes);
        }

        return $this->withErrorHandling(fn () => $this->convertResponse(
            $this->server->completeAuthorizationRequest($authRequest, $psrResponse)
        ), $authRequest->getGrantTypeId() === 'implicit');
    }
}
