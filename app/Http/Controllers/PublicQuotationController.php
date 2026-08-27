<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Catalog\Application\Queries\PublicCatalogReader;
use App\Modules\Checkout\Application\Data\AddressData;
use App\Modules\CRM\Infrastructure\Persistence\Models\Customer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use App\Modules\Quotation\Application\Actions\CreateQuotationDraft;
use App\Modules\Quotation\Application\Actions\ManageQuotationLifecycle;
use App\Modules\Quotation\Application\Data\CreateQuotationCommand;
use App\Modules\Quotation\Infrastructure\Persistence\Models\Quote;
use App\Modules\Quotation\Infrastructure\Persistence\Models\QuoteRevision;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class PublicQuotationController extends Controller
{
    public function show(Request $request, PublicCatalogReader $catalog): View
    {
        $publicId = $request->query('variant');
        $variant = is_string($publicId) ? $catalog->variant($publicId) : null;

        return view('public.quotation', [
            'variant' => $variant,
            'shippingMethods' => $this->shippingMethods(),
            'customerLinked' => ! $request->user() instanceof UserAccount || $this->customer($request) !== null,
        ]);
    }

    public function create(
        Request $request,
        PublicCatalogReader $catalog,
        CreateQuotationDraft $create,
        ManageQuotationLifecycle $lifecycle,
    ): RedirectResponse {
        $validated = $request->validate([
            'variant_public_id' => ['required', 'string', 'size:26', 'regex:/^[0-9A-HJKMNP-TV-Z]+$/'],
            'quantity' => ['required', 'regex:/^\d{1,16}(?:\.\d{1,4})?$/'],
            'operation_key' => ['required', 'string', 'max:100'],
            'recipient_name' => ['required', 'string', 'max:200'],
            'phone' => ['nullable', 'string', 'max:32'],
            'address_line_1' => ['required', 'string', 'max:500'],
            'locality' => ['nullable', 'string', 'max:160'],
            'subdivision' => ['nullable', 'string', 'max:160'],
            'country_code' => ['required', 'in:VN'],
            'shipping_method' => ['required', 'string', 'max:100'],
            'request_note' => ['nullable', 'string', 'max:2000'],
            'invoice_requested' => ['nullable', 'boolean'],
        ]);
        $variant = $catalog->variant((string) $validated['variant_public_id']);
        abort_if($variant === null, 404);
        if (! in_array((string) $validated['shipping_method'], array_keys($this->shippingMethods()), true)) {
            return back()->withInput()->withErrors(['shipping_method' => 'Phương thức giao hàng chưa được cấu hình hoặc đã bị tắt.']);
        }

        $account = $request->user();
        $account = $account instanceof UserAccount ? $account : null;
        $customer = $this->customer($request);
        if ($account !== null && $customer === null) {
            return back()->withInput()->withErrors(['quotation' => 'Tài khoản chưa có hồ sơ Customer đang hoạt động.']);
        }
        $guestToken = $customer === null ? bin2hex(random_bytes(32)) : null;

        try {
            $address = new AddressData(
                trim((string) $validated['recipient_name']),
                trim((string) $validated['address_line_1']),
                (string) $validated['country_code'],
                locality: $this->optional($validated['locality'] ?? null),
                subdivision: $this->optional($validated['subdivision'] ?? null),
                phone: $this->optional($validated['phone'] ?? null),
            );
            $result = $create->execute(new CreateQuotationCommand(
                customerId: $customer?->getKey(),
                companyId: null,
                guestAccessToken: $guestToken,
                lines: [['variant_id' => $variant->id, 'quantity' => (string) $validated['quantity']]],
                billingAddress: $address,
                shippingAddress: $address,
                shippingMethod: (string) $validated['shipping_method'],
                validityDays: (int) config('quotation.default_validity_days'),
                commercialTerms: ['request_note' => $this->optional($validated['request_note'] ?? null)],
                operationKey: (string) $validated['operation_key'],
                proposer: $customer === null ? null : $account,
                abuseKey: $guestToken === null ? null : hash('sha256', (string) $request->ip()."\0".(string) $request->userAgent()),
                paymentMethod: 'bank_transfer',
                invoiceRequested: (bool) ($validated['invoice_requested'] ?? false),
            ));
            if ($guestToken === null) {
                if (! $account instanceof UserAccount) {
                    throw new AuthorizationException('Authenticated quotation actor is unavailable.');
                }
                $revision = $lifecycle->submitCustomer($result->revision, 'public-submit-'.$result->quote->public_id, 0, $account);
            } else {
                $revision = $lifecycle->submitGuest($result->revision, $guestToken, 'public-submit-'.$result->quote->public_id, 0);
            }
        } catch (DomainException|AuthorizationException $exception) {
            report($exception);

            return back()->withInput()->withErrors(['quotation' => 'Chưa thể gửi yêu cầu báo giá: '.$exception->getMessage()]);
        }

        if ($guestToken !== null) {
            $request->session()->put('quote_access.'.$result->quote->public_id, $guestToken);
        }

        return to_route('public.quotation.view', $result->quote->public_id)
            ->with('status', 'Yêu cầu báo giá đã được gửi.');
    }

    public function view(string $quote, Request $request): View
    {
        $record = Quote::query()->where('public_id', $quote)->firstOrFail();
        $account = $request->user();
        $customer = $this->customer($request);
        $owned = $account instanceof UserAccount && $customer !== null && (int) $record->customer_id === (int) $customer->getKey();
        $token = $request->session()->get('quote_access.'.$record->public_id);
        $guest = is_string($token) && is_string($record->guest_access_hash)
            && hash_equals($record->guest_access_hash, hash_hmac('sha256', $token, (string) config('app.key'), true));
        abort_unless($owned || $guest, 404);
        $revision = QuoteRevision::query()->with('lines')->findOrFail($record->current_revision_id);

        return view('public.quotation-view', ['quote' => [
            'publicId' => $record->public_id,
            'revision' => $revision->revision_no,
            'state' => $revision->state,
            'currency' => 'VND',
            'finalAmount' => $revision->final_amount,
            'validityDays' => $revision->requested_validity_days,
            'lines' => $revision->lines->map(fn ($line) => [
                'sku' => $line->sku, 'name' => $line->name, 'quantity' => $line->quantity,
            ])->values()->all(),
        ]]);
    }

    public function access(
        string $quote,
        string $action,
        Request $request,
        ManageQuotationLifecycle $lifecycle,
    ): RedirectResponse {
        $validated = $request->validate(['event_key' => ['required', 'string', 'max:100']]);
        $record = Quote::query()->where('public_id', $quote)->firstOrFail();
        $revision = QuoteRevision::query()->findOrFail($record->current_revision_id);
        $account = $request->user();
        $customer = $this->customer($request);
        $owned = $account instanceof UserAccount && $customer !== null && (int) $record->customer_id === (int) $customer->getKey();
        $token = $request->session()->get('quote_access.'.$record->public_id);
        $guest = is_string($token) && is_string($record->guest_access_hash)
            && hash_equals($record->guest_access_hash, hash_hmac('sha256', $token, (string) config('app.key'), true));
        abort_unless($owned || $guest, 404);

        try {
            if ($owned) {
                match ($action) {
                    'viewed' => $lifecycle->viewCustomer($revision, $account, (string) $validated['event_key']),
                    'accepted' => $lifecycle->acceptCustomer($revision, $account, (string) $validated['event_key']),
                    'rejected' => $lifecycle->rejectCustomer($revision, $account, (string) $validated['event_key']),
                    default => abort(404),
                };
            } elseif (is_string($token)) {
                match ($action) {
                    'viewed' => $lifecycle->viewGuest($revision, $token, (string) $validated['event_key']),
                    'accepted' => $lifecycle->acceptGuest($revision, $token, (string) $validated['event_key']),
                    'rejected' => $lifecycle->rejectGuest($revision, $token, (string) $validated['event_key']),
                    default => abort(404),
                };
            }
        } catch (DomainException|AuthorizationException $exception) {
            return back()->withErrors(['quotation' => 'Không thể cập nhật báo giá: '.$exception->getMessage()]);
        }

        return to_route('public.quotation.view', $record->public_id)->with('status', 'Trạng thái báo giá đã được cập nhật.');
    }

    /** @return array<string, string> */
    private function shippingMethods(): array
    {
        $configured = config('shipping.methods');
        $result = [];
        if (! is_array($configured)) {
            return $result;
        }
        foreach ($configured as $code => $details) {
            if (is_string($code) && is_array($details) && ($details['enabled'] ?? false) === true) {
                $label = $details['label'] ?? $code;
                $result[$code] = is_string($label) ? $label : $code;
            }
        }

        return $result;
    }

    private function customer(Request $request): ?Customer
    {
        $account = $request->user();

        return $account instanceof UserAccount
            ? Customer::query()->where('user_account_id', $account->getKey())->where('status', 'active')->first()
            : null;
    }

    private function optional(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
