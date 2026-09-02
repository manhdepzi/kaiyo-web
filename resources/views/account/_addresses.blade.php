<section class="mt-8" aria-labelledby="addresses-heading">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 id="addresses-heading" class="text-xl font-bold">Sổ địa chỉ</h2>
            <p class="mt-1 text-sm text-ink-muted">Địa chỉ mặc định được dùng để điền nhanh Checkout; mỗi Order vẫn lưu snapshot riêng, không thay đổi theo sổ địa chỉ.</p>
        </div>
        <x-ui.badge tone="info">{{ count($portal->addresses) }}/20 địa chỉ</x-ui.badge>
    </div>

    @error('address')<x-ui.alert class="mt-4" tone="danger" title="Không thể cập nhật sổ địa chỉ">{{ $message }}</x-ui.alert>@enderror

    <div class="mt-5 grid gap-4 lg:grid-cols-2">
        @foreach ($portal->addresses as $address)
            <article class="rounded-panel border border-line bg-surface p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="font-bold">{{ $address['label'] }}</h3>
                        <p class="mt-1 text-sm text-ink-muted">{{ $address['recipient_name'] }}@if($address['phone']) · {{ $address['phone'] }}@endif</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @if($address['is_default_shipping'])<x-ui.badge tone="success">Giao hàng mặc định</x-ui.badge>@endif
                        @if($address['is_default_billing'])<x-ui.badge tone="info">Hóa đơn mặc định</x-ui.badge>@endif
                    </div>
                </div>
                <p class="mt-3 text-sm leading-6">{{ $address['address_line_1'] }}@if($address['address_line_2']), {{ $address['address_line_2'] }}@endif<br>{{ collect([$address['locality'], $address['subdivision'], $address['postal_code']])->filter()->implode(', ') }} · {{ $address['country_code'] }}</p>

                <details class="mt-4 border-t border-line pt-4">
                    <summary class="cursor-pointer font-semibold text-brand">Chỉnh sửa địa chỉ</summary>
                    <form method="POST" action="{{ route('account.addresses.update', $address['public_id']) }}" class="mt-4 grid gap-3 sm:grid-cols-2">
                        @csrf @method('PATCH')
                        <input type="hidden" name="expected_version" value="{{ $address['version'] }}">
                        <x-ui.input :id="'address-'.$address['public_id'].'-label'" name="label" label="Nhãn địa chỉ" :value="$address['label']" required />
                        <x-ui.input :id="'address-'.$address['public_id'].'-recipient'" name="recipient_name" label="Người nhận" :value="$address['recipient_name']" required />
                        <x-ui.input :id="'address-'.$address['public_id'].'-phone'" name="phone" label="Số điện thoại" :value="$address['phone']" />
                        <x-ui.input :id="'address-'.$address['public_id'].'-company'" name="company_name" label="Tên công ty" :value="$address['company_name']" />
                        <x-ui.input :id="'address-'.$address['public_id'].'-tax'" name="tax_code" label="Mã số thuế" :value="$address['tax_code']" />
                        <x-ui.input :id="'address-'.$address['public_id'].'-line-1'" name="address_line_1" label="Địa chỉ" :value="$address['address_line_1']" required />
                        <x-ui.input :id="'address-'.$address['public_id'].'-line-2'" name="address_line_2" label="Địa chỉ bổ sung" :value="$address['address_line_2']" />
                        <x-ui.input :id="'address-'.$address['public_id'].'-locality'" name="locality" label="Quận/Huyện" :value="$address['locality']" />
                        <x-ui.input :id="'address-'.$address['public_id'].'-subdivision'" name="subdivision" label="Tỉnh/Thành phố" :value="$address['subdivision']" />
                        <x-ui.input :id="'address-'.$address['public_id'].'-postal'" name="postal_code" label="Mã bưu chính" :value="$address['postal_code']" />
                        <input type="hidden" name="country_code" value="VN">
                        <label class="flex items-center gap-2 text-sm"><input id="address-{{ $address['public_id'] }}-shipping" type="checkbox" name="is_default_shipping" value="1" @checked($address['is_default_shipping'])> Giao hàng mặc định</label>
                        <label class="flex items-center gap-2 text-sm"><input id="address-{{ $address['public_id'] }}-billing" type="checkbox" name="is_default_billing" value="1" @checked($address['is_default_billing'])> Hóa đơn mặc định</label>
                        <div class="sm:col-span-2"><x-ui.button type="submit" size="sm" icon="check">Lưu địa chỉ</x-ui.button></div>
                    </form>
                    <form method="POST" action="{{ route('account.addresses.destroy', $address['public_id']) }}" class="mt-3">
                        @csrf @method('DELETE')
                        <input type="hidden" name="expected_version" value="{{ $address['version'] }}">
                        <x-ui.button type="submit" variant="danger" size="sm" icon="trash">Ngừng sử dụng</x-ui.button>
                    </form>
                </details>
            </article>
        @endforeach
    </div>

    @if(count($portal->addresses) < 20)
        <x-ui.card class="mt-5" title="Thêm địa chỉ mới" description="Địa chỉ đầu tiên tự động trở thành mặc định giao hàng và hóa đơn.">
            <form method="POST" action="{{ route('account.addresses.store') }}" class="grid gap-4 sm:grid-cols-2">
                @csrf
                <x-ui.input name="label" label="Nhãn địa chỉ" placeholder="Nhà riêng, Văn phòng..." :value="old('label')" required />
                <x-ui.input name="recipient_name" label="Người nhận" :value="old('recipient_name')" required />
                <x-ui.input name="phone" label="Số điện thoại" :value="old('phone')" />
                <x-ui.input name="company_name" label="Tên công ty" :value="old('company_name')" />
                <x-ui.input name="tax_code" label="Mã số thuế" :value="old('tax_code')" />
                <x-ui.input name="address_line_1" label="Địa chỉ" :value="old('address_line_1')" required />
                <x-ui.input name="address_line_2" label="Địa chỉ bổ sung" :value="old('address_line_2')" />
                <x-ui.input name="locality" label="Quận/Huyện" :value="old('locality')" />
                <x-ui.input name="subdivision" label="Tỉnh/Thành phố" :value="old('subdivision')" />
                <x-ui.input name="postal_code" label="Mã bưu chính" :value="old('postal_code')" />
                <input type="hidden" name="country_code" value="VN">
                <div class="space-y-2">
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_default_shipping" value="1" @checked(old('is_default_shipping'))> Đặt làm địa chỉ giao hàng mặc định</label>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_default_billing" value="1" @checked(old('is_default_billing'))> Đặt làm địa chỉ hóa đơn mặc định</label>
                </div>
                <div class="sm:col-span-2"><x-ui.button type="submit" icon="plus">Thêm địa chỉ</x-ui.button></div>
            </form>
        </x-ui.card>
    @endif
</section>
