<div class="no-print">
    <button class="btn" onclick="window.print()">Print</button>

    @if (empty($publicMode))
        <a class="btn" href="{{ route('invoices.show', $invoice) }}">Back</a>
    @endif
</div>
