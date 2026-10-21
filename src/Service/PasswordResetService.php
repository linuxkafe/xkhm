<?php

declare(strict_types=1);

namespace Xkhm\Service;

use Xkhm\Repository\ClientRepositoryInterface;

/**
 * Password reset requests.
 *
 * The answer is **always** the same, whether or not the account exists:
 * telling the caller which e-mail addresses are registered would leak the
 * client list to anyone who types an address into the form.
 */
class PasswordResetService
{
    public function __construct(private ClientRepositoryInterface $clientRepository)
    {
    }

    /**
     * Register a reset request.
     *
     * @return bool whether the account exists — the caller must NOT reveal it
     */
    public function requestReset(string $email): bool
    {
        if (trim($email) === '') {
            return false;
        }

        $client = $this->clientRepository->findByEmail(trim($email));

        return $client !== null;
    }
}
