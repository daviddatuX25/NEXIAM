<script>
	import { cn } from "@/lib/utils.js";
	import { Slider, useId } from "bits-ui";

if (typeof window !== "undefined" && typeof window.ResizeObserver === "undefined") {
	globalThis.ResizeObserver = class {
		observe() {}
		unobserve() {}
		disconnect() {}
	};
}
	const uid = useId();

	let {
		value = $bindable(0),
		label = "",
		min = 0,
		max = 100,
		step = 1,
		unit = "",
		disabled = false,
		id = undefined,
		name = undefined,
		class: className = "",
		oninput = undefined,
		oncommit = undefined,
		...restProps
	} = $props();

	const controlId = $derived(id || `numeric-control-${uid}`);
	const effectiveMin = $derived(Number.isFinite(min) ? min : 0);
	const effectiveMax = $derived(Number.isFinite(max) ? Math.max(effectiveMin, max) : 100);
	const effectiveStep = $derived(Number.isFinite(step) && step > 0 ? step : 1);

	function getStepPrecision(stepVal) {
		if (!Number.isFinite(stepVal) || stepVal <= 0) return 0;
		const str = stepVal.toString();
		if (str.includes(".")) {
			return str.split(".")[1].length;
		}
		const expMatch = str.match(/e-(\d+)/);
		if (expMatch) {
			return parseInt(expMatch[1], 10);
		}
		return 0;
	}

	function roundToStep(val, stepVal, minVal = 0) {
		if (!Number.isFinite(val)) return minVal;
		if (!Number.isFinite(stepVal) || stepVal <= 0) return val;
		const precision = getStepPrecision(stepVal);
		const offset = val - minVal;
		const numSteps = Math.round(offset / stepVal);
		const snapped = minVal + numSteps * stepVal;
		return Number(snapped.toFixed(precision));
	}

	function formatNumber(val, stepVal) {
		if (!Number.isFinite(val)) return "";
		const precision = getStepPrecision(stepVal);
		if (precision > 0) {
			return Number(val.toFixed(precision)).toString();
		}
		return val.toString();
	}

	const clampedValue = $derived(
		Math.max(effectiveMin, Math.min(effectiveMax, Number.isFinite(value) ? value : effectiveMin))
	);

	let inputValue = $state("");
	let lastSyncedValue = $state(NaN);
	let lastCommittedValue = $state(NaN);

	$effect(() => {
		const current = clampedValue;
		if (current !== lastSyncedValue) {
			lastSyncedValue = current;
			lastCommittedValue = current;
			inputValue = formatNumber(current, effectiveStep);
		}
	});

	const isInvalid = $derived.by(() => {
		const trimmed = inputValue.trim();
		if (trimmed === "" || trimmed === "-" || trimmed === ".") return true;
		const num = Number(trimmed);
		if (Number.isNaN(num)) return true;
		if (num < effectiveMin || num > effectiveMax) return true;
		return false;
	});

	function handleInputChange(e) {
		const text = e.currentTarget.value;
		inputValue = text;
		const num = parseFloat(text);
		if (!Number.isNaN(num) && num >= effectiveMin && num <= effectiveMax) {
			const precision = getStepPrecision(effectiveStep);
			const normalized = precision > 0 ? Number(num.toFixed(precision + 2)) : num;
			lastSyncedValue = normalized;
			value = normalized;
			oninput?.(normalized);
		}
	}

	function commitInputValue() {
		if (disabled) return;
		const raw = inputValue.trim();
		let num = parseFloat(raw);
		if (Number.isNaN(num)) {
			num = Number.isFinite(lastCommittedValue) ? lastCommittedValue : clampedValue;
		} else {
			num = Math.max(effectiveMin, Math.min(effectiveMax, num));
			num = roundToStep(num, effectiveStep, effectiveMin);
		}
		lastSyncedValue = num;
		lastCommittedValue = num;
		value = num;
		inputValue = formatNumber(num, effectiveStep);
		oncommit?.(num);
	}

	function handleInputBlur() {
		commitInputValue();
	}

	function handleInputKeyDown(e) {
		if (disabled) return;

		if (e.key === "Enter") {
			e.preventDefault();
			commitInputValue();
			e.currentTarget.blur();
		} else if (e.key === "Escape") {
			e.preventDefault();
			const revertVal = Number.isFinite(lastCommittedValue) ? lastCommittedValue : clampedValue;
			lastSyncedValue = revertVal;
			value = revertVal;
			const formatted = formatNumber(revertVal, effectiveStep);
			inputValue = formatted;
			e.currentTarget.value = formatted;
			e.currentTarget.blur();
		} else if (e.key === "ArrowUp") {
			e.preventDefault();
			const multiplier = e.shiftKey ? 10 : e.altKey || e.ctrlKey ? 0.1 : 1;
			const delta = effectiveStep * multiplier;
			const current = Number.isFinite(value) ? value : effectiveMin;
			const next = roundToStep(Math.min(effectiveMax, current + delta), effectiveStep, effectiveMin);
			lastSyncedValue = next;
			lastCommittedValue = next;
			value = next;
			const formatted = formatNumber(next, effectiveStep);
			inputValue = formatted;
			e.currentTarget.value = formatted;
			oninput?.(next);
			oncommit?.(next);
		} else if (e.key === "ArrowDown") {
			e.preventDefault();
			const multiplier = e.shiftKey ? 10 : e.altKey || e.ctrlKey ? 0.1 : 1;
			const delta = effectiveStep * multiplier;
			const current = Number.isFinite(value) ? value : effectiveMin;
			const next = roundToStep(Math.max(effectiveMin, current - delta), effectiveStep, effectiveMin);
			lastSyncedValue = next;
			lastCommittedValue = next;
			value = next;
			const formatted = formatNumber(next, effectiveStep);
			inputValue = formatted;
			e.currentTarget.value = formatted;
			oninput?.(next);
			oncommit?.(next);
		}
	}

	function handleSliderChange(newVal) {
		if (disabled) return;
		const valNum = Array.isArray(newVal) ? newVal[0] : newVal;
		const snapped = roundToStep(valNum, effectiveStep, effectiveMin);
		const clamped = Math.max(effectiveMin, Math.min(effectiveMax, snapped));
		lastSyncedValue = clamped;
		value = clamped;
		inputValue = formatNumber(clamped, effectiveStep);
		oninput?.(clamped);
	}

	function handleSliderCommit(newVal) {
		if (disabled) return;
		const valNum = Array.isArray(newVal) ? newVal[0] : newVal;
		const snapped = roundToStep(valNum, effectiveStep, effectiveMin);
		const clamped = Math.max(effectiveMin, Math.min(effectiveMax, snapped));
		lastSyncedValue = clamped;
		lastCommittedValue = clamped;
		value = clamped;
		inputValue = formatNumber(clamped, effectiveStep);
		oncommit?.(clamped);
	}
</script>

<div
	class={cn(
		"group/numeric-control flex flex-col gap-1.5 text-xs text-studio-panel-foreground",
		disabled && "opacity-50 pointer-events-none cursor-not-allowed select-none",
		className
	)}
	{...restProps}
>
	<div class="flex items-center justify-between gap-2">
		{#if label}
			<label
				for={controlId}
				class="font-medium text-studio-panel-foreground/80 select-none truncate text-xs cursor-pointer"
				title={label}
			>
				{label}
			</label>
		{/if}
		<div class="relative ml-auto flex items-center">
			<input
				id={controlId}
				{name}
				type="text"
				inputmode="decimal"
				role="spinbutton"
				{disabled}
				aria-label={label || "Numeric value"}
				aria-valuemin={effectiveMin}
				aria-valuemax={effectiveMax}
				aria-valuenow={clampedValue}
				aria-invalid={isInvalid}
				value={inputValue}
				oninput={handleInputChange}
				onblur={handleInputBlur}
				onkeydown={handleInputKeyDown}
				class={cn(
					"h-7 w-20 rounded-md border border-studio-border bg-studio-panel px-2 font-mono text-xs text-right text-studio-panel-foreground shadow-xs transition-colors motion-reduce:transition-none outline-hidden focus-visible:border-studio-focus focus-visible:ring-2 focus-visible:ring-studio-focus/40",
					unit ? "pr-6" : "pr-2",
					isInvalid && "border-destructive text-destructive focus-visible:border-destructive focus-visible:ring-destructive/30",
					disabled && "cursor-not-allowed opacity-50"
				)}
			/>
			{#if unit}
				<span
					class="pointer-events-none absolute right-2 select-none text-[10px] font-medium text-studio-panel-muted-foreground"
					aria-hidden="true"
				>
					{unit}
				</span>
			{/if}
		</div>
	</div>

	<div class="flex items-center gap-2 pt-0.5">
		<Slider.Root
			type="single"
			value={clampedValue}
			min={effectiveMin}
			max={effectiveMax}
			step={effectiveStep}
			{disabled}
			onValueChange={handleSliderChange}
			onValueCommit={handleSliderCommit}
			class="relative flex w-full touch-none select-none items-center py-1 cursor-pointer disabled:cursor-not-allowed"
			aria-label={label ? `${label} slider` : "Range slider"}
		>
			{#snippet children()}
				<span class="relative h-1.5 w-full grow overflow-hidden rounded-full bg-studio-panel-muted">
					<Slider.Range class="absolute h-full bg-studio-focus transition-all motion-reduce:transition-none" />
				</span>
				<Slider.Thumb
					index={0}
					class="block size-3.5 rounded-full border border-studio-focus/50 bg-studio-panel shadow-xs ring-offset-studio-panel transition-transform motion-reduce:transition-none hover:scale-110 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-studio-focus focus-visible:ring-offset-1 disabled:pointer-events-none disabled:opacity-50"
					aria-label={label ? `${label} thumb` : "Slider thumb"}
				/>
			{/snippet}
		</Slider.Root>
	</div>
</div>
