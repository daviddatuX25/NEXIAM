// @vitest-environment jsdom

import { cleanup, fireEvent, render } from '@testing-library/svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import NumericControl from '../../resources/js/Components/studio/NumericControl.svelte';

describe('NumericControl Studio Component', () => {
	beforeEach(() => {
		vi.stubGlobal(
			'ResizeObserver',
			class {
				observe() {}
				unobserve() {}
				disconnect() {}
			}
		);
	});

	afterEach(() => {
		cleanup();
		vi.unstubAllGlobals();
	});

	it('renders with accessible label, spinbutton role and initial value', () => {
		const { getByRole, getByText } = render(NumericControl, {
			label: 'Sheet Width',
			value: 210,
			min: 50,
			max: 500,
			step: 1,
			unit: 'mm',
		});

		expect(getByText('Sheet Width')).not.toBeNull();
		expect(getByText('mm')).not.toBeNull();

		const input = getByRole('spinbutton') as HTMLInputElement;
		expect(input.value).toBe('210');
		expect(input.getAttribute('aria-valuemin')).toBe('50');
		expect(input.getAttribute('aria-valuemax')).toBe('500');
		expect(input.getAttribute('aria-valuenow')).toBe('210');
		expect(input.getAttribute('aria-label')).toBe('Sheet Width');
	});

	it('clamps out-of-bounds initial values to min and max', () => {
		const { getByRole } = render(NumericControl, {
			label: 'Opacity',
			value: 150,
			min: 0,
			max: 100,
		});

		const input = getByRole('spinbutton') as HTMLInputElement;
		expect(input.value).toBe('100');
		expect(input.getAttribute('aria-valuenow')).toBe('100');
	});

	it('handles decimal steps and formats without precision artifacts', () => {
		const { getByRole } = render(NumericControl, {
			label: 'Grid Pitch',
			value: 4.5,
			min: 1,
			max: 10,
			step: 0.25,
			unit: 'mm',
		});

		const input = getByRole('spinbutton') as HTMLInputElement;
		expect(input.value).toBe('4.5');
	});

	it('fires oninput during live typing and oncommit on blur/commit', async () => {
		const oninput = vi.fn();
		const oncommit = vi.fn();

		const { getByRole } = render(NumericControl, {
			label: 'Margin',
			value: 20,
			min: 0,
			max: 100,
			step: 1,
			oninput,
			oncommit,
		});

		const input = getByRole('spinbutton') as HTMLInputElement;

		// Live typing within range triggers oninput
		await fireEvent.input(input, { target: { value: '35' } });
		expect(oninput).toHaveBeenCalledWith(35);
		expect(oncommit).not.toHaveBeenCalled();

		// Blur commits the value
		await fireEvent.blur(input);
		expect(oncommit).toHaveBeenCalledWith(35);
	});

	it('shows invalid state when typing out-of-range numbers and clamps on commit', async () => {
		const oncommit = vi.fn();

		const { getByRole } = render(NumericControl, {
			label: 'Scale',
			value: 50,
			min: 10,
			max: 100,
			oncommit,
		});

		const input = getByRole('spinbutton') as HTMLInputElement;

		// Out of range entry
		await fireEvent.input(input, { target: { value: '999' } });
		expect(input.getAttribute('aria-invalid')).toBe('true');

		// On blur/commit, it clamps to max (100)
		await fireEvent.blur(input);
		expect(input.value).toBe('100');
		expect(input.getAttribute('aria-invalid')).toBe('false');
		expect(oncommit).toHaveBeenCalledWith(100);
	});

	it('supports keyboard navigation with ArrowUp, ArrowDown, Shift multiplier, and Enter commit', async () => {
		const oninput = vi.fn();
		const oncommit = vi.fn();

		const { getByRole } = render(NumericControl, {
			label: 'Height',
			value: 50,
			min: 0,
			max: 200,
			step: 5,
			oninput,
			oncommit,
		});

		const input = getByRole('spinbutton') as HTMLInputElement;

		// ArrowUp increments by step (5) -> 55
		await fireEvent.keyDown(input, { key: 'ArrowUp' });
		expect(input.value).toBe('55');
		expect(oninput).toHaveBeenCalledWith(55);
		expect(oncommit).toHaveBeenCalledWith(55);

		// Shift + ArrowUp increments by 10x step (50) -> 105
		await fireEvent.keyDown(input, { key: 'ArrowUp', shiftKey: true });
		expect(input.value).toBe('105');
		expect(oninput).toHaveBeenCalledWith(105);
		expect(oncommit).toHaveBeenCalledWith(105);

		// ArrowDown decrements by step (5) -> 100
		await fireEvent.keyDown(input, { key: 'ArrowDown' });
		expect(input.value).toBe('100');

		// Enter commits
		await fireEvent.keyDown(input, { key: 'Enter' });
		expect(oncommit).toHaveBeenCalledWith(100);
	});

	it('reverts to previous valid value on Escape key', async () => {
		const { getByRole } = render(NumericControl, {
			label: 'Radius',
			value: 12,
			min: 0,
			max: 50,
		});

		const input = getByRole('spinbutton') as HTMLInputElement;

		await fireEvent.input(input, { target: { value: '44' } });
		expect(input.value).toBe('44');

		await fireEvent.keyDown(input, { key: 'Escape' });
		expect(input.value).toBe('12');
	});

	it('respects disabled state by preventing input and interactions', async () => {
		const oninput = vi.fn();
		const oncommit = vi.fn();

		const { getByRole } = render(NumericControl, {
			label: 'Locked Dimension',
			value: 30,
			min: 0,
			max: 100,
			disabled: true,
			oninput,
			oncommit,
		});

		const input = getByRole('spinbutton') as HTMLInputElement;
		expect(input.disabled).toBe(true);

		await fireEvent.keyDown(input, { key: 'ArrowUp' });
		expect(oninput).not.toHaveBeenCalled();
		expect(oncommit).not.toHaveBeenCalled();
	});
});
