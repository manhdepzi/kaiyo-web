<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Actions;

use App\Modules\CRM\Application\Support\AuthorizesCrm;
use App\Modules\CRM\Infrastructure\Persistence\Models\Contact;
use App\Modules\CRM\Support\CrmIdentityNormalizer;
use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use DomainException;

final readonly class CreateContact
{
    use AuthorizesCrm;

    public function __construct(private PermissionAuthorizer $authorizer, private CrmIdentityNormalizer $normalizer) {}

    public function execute(UserAccount $actor, string $name, ?int $customerId, ?int $companyId, ?string $email = null, ?string $phone = null): Contact
    {
        if (($customerId === null) === ($companyId === null)) {
            throw new DomainException('A Contact must belong to exactly one Customer or Company.');
        }
        $scope = $customerId !== null ? AuthorizationScope::customer('crm', $customerId) : AuthorizationScope::company('crm', (int) $companyId);
        $this->authorize($this->authorizer, $actor, 'crm.contacts.manage', $scope);

        return Contact::query()->create([
            'customer_id' => $customerId,
            'company_id' => $companyId,
            'name' => trim($name),
            'email_display' => $email === null ? null : trim($email),
            'email_normalized' => $email === null ? null : $this->normalizer->email($email),
            'phone_display' => $phone === null ? null : trim($phone),
            'phone_e164' => $phone === null ? null : $this->normalizer->phone($phone),
            'status' => 'active',
        ]);
    }
}
