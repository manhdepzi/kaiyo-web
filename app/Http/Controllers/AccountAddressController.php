<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\CRM\Application\Actions\CreateOwnCustomerAddress;
use App\Modules\CRM\Application\Actions\DeactivateOwnCustomerAddress;
use App\Modules\CRM\Application\Actions\UpdateOwnCustomerAddress;
use App\Modules\CRM\Application\Data\CustomerAddressData;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class AccountAddressController extends Controller
{
    public function store(Request $request, CreateOwnCustomerAddress $addresses): RedirectResponse
    {
        $account = $request->user();
        abort_unless($account instanceof UserAccount, 404);

        try {
            $addresses->execute($account, $this->data($request));
        } catch (DomainException $exception) {
            return to_route('account')->withInput()->withErrors(['address' => 'Chưa thể thêm địa chỉ: '.$exception->getMessage()]);
        }

        return to_route('account')->with('status', 'Địa chỉ đã được thêm.');
    }

    public function update(Request $request, string $address, UpdateOwnCustomerAddress $addresses): RedirectResponse
    {
        $account = $request->user();
        abort_unless($account instanceof UserAccount, 404);
        $expectedVersion = $request->validate(['expected_version' => ['required', 'integer', 'min:0']])['expected_version'];

        try {
            $addresses->execute($account, $address, (int) $expectedVersion, $this->data($request));
        } catch (DomainException $exception) {
            return to_route('account')->withInput()->withErrors(['address' => 'Chưa thể cập nhật địa chỉ: '.$exception->getMessage()]);
        }

        return to_route('account')->with('status', 'Địa chỉ đã được cập nhật.');
    }

    public function destroy(Request $request, string $address, DeactivateOwnCustomerAddress $addresses): RedirectResponse
    {
        $account = $request->user();
        abort_unless($account instanceof UserAccount, 404);
        $expectedVersion = $request->validate(['expected_version' => ['required', 'integer', 'min:0']])['expected_version'];

        try {
            $addresses->execute($account, $address, (int) $expectedVersion);
        } catch (DomainException $exception) {
            return to_route('account')->withErrors(['address' => 'Chưa thể ngừng sử dụng địa chỉ: '.$exception->getMessage()]);
        }

        return to_route('account')->with('status', 'Địa chỉ đã được ngừng sử dụng.');
    }

    private function data(Request $request): CustomerAddressData
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'recipient_name' => ['required', 'string', 'max:200'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'tax_code' => ['nullable', 'string', 'max:64'],
            'address_line_1' => ['required', 'string', 'max:500'],
            'address_line_2' => ['nullable', 'string', 'max:500'],
            'locality' => ['nullable', 'string', 'max:160'],
            'subdivision' => ['nullable', 'string', 'max:160'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'country_code' => ['required', 'in:VN'],
            'phone' => ['nullable', 'string', 'max:32'],
            'is_default_shipping' => ['nullable', 'boolean'],
            'is_default_billing' => ['nullable', 'boolean'],
        ]);

        return new CustomerAddressData(
            label: (string) $validated['label'],
            recipientName: (string) $validated['recipient_name'],
            addressLine1: (string) $validated['address_line_1'],
            countryCode: (string) $validated['country_code'],
            companyName: $this->optional($validated['company_name'] ?? null),
            taxCode: $this->optional($validated['tax_code'] ?? null),
            addressLine2: $this->optional($validated['address_line_2'] ?? null),
            locality: $this->optional($validated['locality'] ?? null),
            subdivision: $this->optional($validated['subdivision'] ?? null),
            postalCode: $this->optional($validated['postal_code'] ?? null),
            phone: $this->optional($validated['phone'] ?? null),
            defaultShipping: (bool) ($validated['is_default_shipping'] ?? false),
            defaultBilling: (bool) ($validated['is_default_billing'] ?? false),
        );
    }

    private function optional(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
