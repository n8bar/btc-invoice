<x-app-layout>
    <x-slot name="header">
        @php $st = $invoice->status ?? 'draft'; @endphp
        <h2 class="text-xl font-semibold leading-tight">
            Invoice <span class="text-gray-500">#{{ $invoice->number }}</span>
            <span class="ml-2 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
      @switch($st)
        @case('paid') bg-green-100 text-green-800 @break
        @case('sent') bg-blue-100 text-blue-800 @break
        @case('void') bg-yellow-100 text-yellow-800 @break
        @default bg-gray-100 text-gray-800
      @endswitch">
      {{ strtoupper($st) }}
            </span>
        </h2>
    </x-slot>


    <div class="py-8">
        <div class="mx-auto max-w-5xl sm:px-6 lg:px-8 space-y-6">

            <div class="flex items-center justify-between">
                <a href="{{ route('invoices.index') }}" class="text-sm text-gray-600 hover:underline">← Back to Invoices</a>
                @php
                    $st = $invoice->status ?? 'draft';
                    $canMarkSent = !in_array($st, ['sent','paid','void']);
                    $canMarkPaid = !in_array($st, ['paid','void']);
                    $canVoid     = $st !== 'void';
                @endphp

                <div class="flex items-center gap-2">
                    {{-- Mark sent --}}
                    <form method="POST" action="{{ route('invoices.set-status', ['invoice'=>$invoice,'action'=>'sent']) }}" class="inline">
                        @csrf @method('PATCH')
                        <x-secondary-button type="submit" :disabled="!$canMarkSent">
                            Mark sent
                        </x-secondary-button>
                    </form>

                    {{-- Mark paid --}}
                    <form method="POST" action="{{ route('invoices.set-status', ['invoice'=>$invoice,'action'=>'paid']) }}" class="inline">
                        @csrf @method('PATCH')
                        <x-secondary-button type="submit" :disabled="!$canMarkPaid">
                            Mark paid
                        </x-secondary-button>
                    </form>

                    {{-- Void --}}
                    <form method="POST"
                          action="{{ route('invoices.set-status', ['invoice'=>$invoice,'action'=>'void']) }}"
                          class="inline"
                          onsubmit="return confirm('Void invoice {{ $invoice->number }}? ');">
                        @csrf @method('PATCH')
                        <x-danger-button type="submit" :disabled="!$canVoid">Void</x-danger-button>
                    </form>

                    {{-- Reset to draft (undo) --}}
                    @if ($st !== 'draft')
                        <form method="POST" action="{{ route('invoices.set-status', ['invoice'=>$invoice,'action'=>'draft']) }}" class="inline">
                            @csrf @method('PATCH')
                            <x-secondary-button type="submit" >Reset to draft</x-secondary-button>
                        </form>
                    @endif

                    <a href="{{ route('invoices.print', $invoice) }}"
                       target="_blank" rel="noopener"
                       class="inline-flex items-center rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                        Print
                    </a>

                </div>

            </div>



            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="grid grid-cols-1 gap-0 md:grid-cols-2">
                    <div class="p-6 border-b md:border-b-0 md:border-r">
                        <h3 class="mb-3 text-sm font-semibold text-gray-700">Summary</h3>
                        <dl class="space-y-2 text-sm">
                            <div class="flex justify-between"><dt class="text-gray-600">Client</dt><dd>{{ $invoice->client->name ?? '—' }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-600">Status</dt><dd class="uppercase">{{ $invoice->status ?? 'draft' }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-600">Invoice date</dt><dd>{{ optional($invoice->invoice_date)->toDateString() ?: '—' }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-600">Due date</dt><dd>{{ optional($invoice->due_date)->toDateString() ?: '—' }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-600">Paid at</dt><dd>{{ optional($invoice->paid_at)->toDateTimeString() ?: '—' }}</dd></div>
                        </dl>
                    </div>

                    <div class="p-6 border-b">
                        <h3 class="mb-2 text-sm font-semibold text-gray-700">Description</h3>
                        <p class="text-sm text-gray-800 whitespace-pre-line">{{ $invoice->description ?: '—' }}</p>
                    </div>

                    <div class="p-6">
                        <h3 class="mb-3 text-sm font-semibold text-gray-700">Amounts</h3>
                        <dl class="space-y-2 text-sm">
                            <div class="flex justify-between"><dt class="text-gray-600">USD</dt><dd>${{ number_format($invoice->amount_usd, 2) }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-600">BTC rate (USD/BTC)</dt><dd>{{ $invoice->btc_rate ?? '—' }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-600">BTC</dt><dd>{{ $invoice->amount_btc ?? '—' }}</dd></div>
                        </dl>
                    </div>
                </div>

                <div class="p-6 border-t"> <!-- ---------------------------------------     Payment Details    ----------------------------------------------------- -->
                    <h3 class="mb-2 text-sm font-semibold text-gray-700">Payment Details</h3>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-gray-600">BTC address</dt>
                            <dd class="font-mono flex items-center gap-2">
                                <span>{{ $invoice->btc_address ?: '-' }}</span>
                                @if ($invoice->btc_address)
                                    <x-secondary-button type="button" data-copy-text="{{ $invoice->btc_address }}">Copy</x-secondary-button>
                                @endif
                            </dd>
                        </div>



                        @php $st = $invoice->status ?? 'draft'; @endphp
                        <div class="flex justify-between">
                            <dt class="text-gray-600">TXID</dt>
                            <dd class="font-mono flex items-center gap-2">
                                @if ($invoice->txid)
                                    <span>{{ \Illuminate\Support\Str::limit($invoice->txid, 18, '…') }}</span>
                                    <x-secondary-button type="button" data-copy-text="{{ $invoice->txid }}">Copy</x-secondary-button>
                                @else
                                    <span>-</span>
                                @endif
                            </dd>
                        </div>

                        @if (empty($invoice->txid))
                            <p class="mt-1 text-xs text-gray-500">
                                @if (in_array($st, ['draft','sent']))
                                    A TXID appears after the on-chain payment is received.
                                @elseif ($st === 'paid')
                                    Marked paid without recording a TXID. You can add one later for reference.
                                @endif
                            </p>
                        @endif

                        @php $uri = $invoice->bitcoin_uri; @endphp

                        @if ($uri)
                            <div class="flex justify-between">
                                <dt class="text-gray-600">Bitcoin URI</dt>
                                <dd class="font-mono flex items-center gap-2">
                                    <a href="{{ $uri }}" class="text-indigo-600 hover:underline">
                                        {{ \Illuminate\Support\Str::limit($uri, 48) }}
                                    </a>
                                    <x-secondary-button type="button" data-copy-text="{{ $uri }}">Copy</x-secondary-button>
                                </dd>
                            </div>

                            <div class="mt-6 flex flex-col md:flex-row gap-8 md:items-center">
                                <!-- Left: QR (fixed width) -->
                                <div class="md:w-[260px] md:flex-none">
                                    <h3 class="mb-2 text-sm font-semibold text-gray-700">Payment QR</h3>
                                    {{--<canvas id="qrBitcoin" width="220" height="220"
                                            class="rounded-lg border border-gray-200 bg-white p-2"></canvas>--}}
                                    {!! \SimpleSoftwareIO\QrCode\Facades\QrCode::size(220)->margin(1)->generate($invoice->bitcoin_uri) !!}

                                    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
                                    <script>
                                        document.addEventListener('DOMContentLoaded', () => {
                                            const uri = @json($invoice->bitcoin_uri);
                                            const img = document.getElementById('qrBitcoin'); // <-- your <img id="qrBitcoin">
                                            if (!uri || !img) return;

                                            QRCode.toDataURL(uri, { width: 220, margin: 1, errorCorrectionLevel: 'M' }, (err, url) => {
                                                if (err) return console.error('QR error:', err);
                                                img.src = url;
                                            });
                                        });
                                    </script>

                                    <p class="mt-2 text-xs text-gray-500">Scan with any Bitcoin wallet.</p>
                                </div>

                                <!-- Right: big centered Thank you -->
                                <div class="flex-1 min-h-[220px] flex items-center justify-center">
                                    <div class="select-none text-6xl md:text-7xl font-extrabold leading-none tracking-tight">
                                        <span class="text-indigo-950">Thank&nbsp;you!</span>
                                    </div>
                                </div>
                            </div>





                            {{-- lightweight client-side QR (no data sent off-box) --}}
                            <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
                            <script>
                                document.addEventListener('DOMContentLoaded', () => {
                                    const canvas = document.getElementById('qrBitcoin');
                                    const uri = @json($uri);
                                    if (canvas && uri) {
                                        QRCode.toCanvas(canvas, uri, { width: 180, margin: 2 }, err => {
                                            if (err) console.error('QR render failed', err);
                                        });
                                    }
                                });
                            </script>
                        @endif

                    </dl>
                </div>

            </div>

            {{-- Public link (shareable print view) --}}
            <div class="mt-6 rounded-lg border border-gray-200 bg-white p-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-700">Public link</h3>
                    @if ($invoice->public_enabled && $invoice->public_url)
                        <div class="flex items-center gap-2">
                            <form action="{{ route('invoices.share.rotate', $invoice) }}" method="POST"
                                  onsubmit="return confirm('Regenerate public link? Old URL will stop working.');">
                                @csrf @method('PATCH')
                                <x-secondary-button type="submit">Rotate link</x-secondary-button>
                            </form>

                            <form action="{{ route('invoices.share.disable', $invoice) }}" method="POST"
                                  onsubmit="return confirm('Disable public link?');">
                                @csrf @method('PATCH')
                                <x-danger-button>Disable</x-danger-button>
                            </form>
                        </div>
                    @endif
                </div>

                @if (session('public_url'))
                    <div class="mt-2 rounded bg-green-50 p-2 text-sm text-green-700">
                        Link enabled:
                        <a href="{{ session('public_url') }}" target="_blank" rel="noopener" class="underline break-all">
                            {{ session('public_url') }}
                        </a>
                    </div>
                @endif

                @if ($invoice->public_enabled && $invoice->public_url)
                    <p>
                        <div class="mt-3">
                            <div class="flex items-center gap-2">
                                <input type="text" readonly class="w-full rounded-md border-gray-300" value="{{ $invoice->public_url }}">
                                <x-secondary-button type="button" data-copy-text="{{ $invoice->public_url }}">Copy</x-secondary-button>
                                <a href="{{ $invoice->public_url }}" target="_blank" rel="noopener"
                                   class="inline-flex items-center rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                                    Open
                                </a>
                            </div>
                            @if ($invoice->public_expires_at)
                                <p class="mt-2 text-xs text-gray-500">Expires {{ $invoice->public_expires_at->toDayDateTimeString() }}</p>
                            @endif
                        </div>
                    </p>
                @else
                    <form action="{{ route('invoices.share.enable', $invoice) }}" method="POST" class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3 sm:items-end">
                        @csrf @method('PATCH')

                        <div>
                            <label class="block text-xs font-medium text-gray-600">Expiry preset</label>
                            <select name="expires_preset" class="mt-1 w-full rounded-md border-gray-300">
                                <option value="none" selected>No expiry</option>
                                <option value="24h">24 hours</option>
                                <option value="7d">7 days</option>
                                <option value="30d">30 days</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600">Or pick exact datetime</label>
                            <input type="datetime-local" name="expires" class="mt-1 w-full rounded-md border-gray-300">
                            <p class="mt-1 text-[11px] text-gray-500">If set, this overrides the preset.</p>
                        </div>

                        <div>
                            <x-primary-button class="w-full sm:w-auto">Enable public link</x-primary-button>
                        </div>
                    </form>

                    <p class="mt-2 text-xs text-gray-500">
                        Creates a secret link to a read-only print view that refreshes the BTC rate on each visit.
                    </p>
                @endif

                @if($invoice->public_enabled)
                    <br />
                    <div class="mb-4 rounded-md border border-yellow-300 bg-yellow-50 p-3 text-sm text-yellow-800">
                        <p>
                            This invoice is currently public. To edit, first
                            <form action="{{ route('invoices.share.disable', $invoice) }}" method="POST" class="inline"
                                  onsubmit="return confirm('Disable the public link?');">
                                @csrf @method('PATCH')
                                <button type="submit" class="underline text-red-600 hover:text-red-700">disable the public link</button>
                            </form>.
                        </p>
                    </div>
                @endif
            </div>

        </div>
    </div>
    <br />

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            async function copyText(text) {
                try {
                    if (navigator.clipboard && window.isSecureContext) {
                        await navigator.clipboard.writeText(text);
                    } else {
                        const ta = document.createElement('textarea');
                        ta.value = text;
                        ta.style.position = 'fixed';
                        ta.style.top = '-1000px';
                        document.body.appendChild(ta);
                        ta.focus();
                        ta.select();
                        document.execCommand('copy');
                        ta.remove();
                    }
                    return true;
                } catch { return false; }
            }

            document.querySelectorAll('[data-copy-text]').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const text = btn.getAttribute('data-copy-text') || '';
                    const ok = await copyText(text);
                    const old = btn.textContent;
                    btn.textContent = ok ? 'Copied' : 'Copy failed';
                    setTimeout(() => btn.textContent = old, 1200);
                });
            });
        });
    </script>


</x-app-layout>

