<script>
    let {
        quantity = 1,
        copyPolicy = 'unique_instance',
        qrMode = 'qr_signed',
        disabled = false,
        busy = false,
        onquantity = () => {},
        oncopyPolicy = () => {},
        onprint = () => {},
    } = $props();

    const isReusableMaster = $derived(copyPolicy === 'reusable_master');
</script>

<section
    class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
    aria-labelledby="blank-print-title"
>
    <h2 id="blank-print-title" class="font-semibold text-slate-950">
        Print Answer Sheets
    </h2>
    <p class="mt-1 max-w-3xl text-sm text-slate-600">
        {qrMode === 'qr_disabled'
            ? 'Printing records a QR-free issuance with the template version, ordered pages, and intended copies. Capture and pairing are not available for this paper yet.'
            : 'Each signed page identifies its template version and page. Choose how many ordinary capture claims each printed set permits.'}
    </p>

    <fieldset class="mt-5 grid gap-3 md:grid-cols-2">
        <legend class="mb-2 text-sm font-semibold text-slate-800"
            >Copy policy</legend
        >
        <label
            class="flex cursor-pointer gap-3 rounded-xl border border-slate-200 p-4 has-[:checked]:border-slate-950 has-[:checked]:bg-slate-50"
        >
            <input
                type="radio"
                name="copy-policy"
                value="unique_instance"
                checked={copyPolicy === 'unique_instance'}
                onchange={() => oncopyPolicy('unique_instance')}
            />
            <span>
                <span class="block text-sm font-semibold text-slate-950"
                    >Unique sheets</span
                >
                <span class="mt-1 block text-xs leading-5 text-slate-600">
                    {qrMode === 'qr_disabled'
                        ? 'Print one QR-free set per intended copy.'
                        : 'Print one signed set per intended use. Each page accepts one ordinary capture claim.'}
                </span>
            </span>
        </label>
        <label
            class="flex cursor-pointer gap-3 rounded-xl border border-slate-200 p-4 has-[:checked]:border-slate-950 has-[:checked]:bg-slate-50"
        >
            <input
                type="radio"
                name="copy-policy"
                value="reusable_master"
                checked={copyPolicy === 'reusable_master'}
                onchange={() => oncopyPolicy('reusable_master')}
            />
            <span>
                <span class="block text-sm font-semibold text-slate-950"
                    >Reusable master</span
                >
                <span class="mt-1 block text-xs leading-5 text-slate-600">
                    {qrMode === 'qr_disabled'
                        ? 'Print one QR-free master for controlled photocopying; record the intended copy count.'
                        : 'Print one signed master for controlled photocopying. Its pages accept multiple copy claims.'}
                </span>
            </span>
        </label>
    </fieldset>

    <div class="mt-4 flex flex-wrap items-end gap-3">
        <label class="text-sm font-medium text-slate-700">
            {isReusableMaster ? 'Intended copies' : 'Sheets to print'}
            <input
                class="mt-1 block w-32 rounded border-slate-300"
                type="number"
                min="1"
                max="10000"
                value={quantity}
                onchange={(event) =>
                    onquantity(Number(event.currentTarget.value))}
            />
        </label>
        <button
            class="rounded bg-slate-950 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
            type="button"
            onclick={onprint}
            disabled={disabled || busy}
        >
            {busy
                ? 'Queueing…'
                : isReusableMaster
                  ? 'Queue reusable master'
                  : 'Queue unique sheets'}
        </button>
    </div>

    <p class="mt-4 rounded bg-amber-50 p-3 text-xs text-amber-900">
        Print at 100% / actual size — do not fit to page.
        {qrMode === 'qr_disabled'
            ? isReusableMaster
                ? `The ZIP contains one QR-free master recorded for ${quantity} intended copies.`
                : 'The ZIP contains one QR-free set per intended copy with an ordered issuance manifest.'
            : isReusableMaster
              ? `The ZIP contains one signed master recorded for ${quantity} intended copies.`
              : 'Requests render in deterministic chunks of 100 with ordered ZIP manifests.'}
    </p>
</section>
