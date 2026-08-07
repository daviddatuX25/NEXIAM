<?php

test('the application bootstraps with a framework version', function (): void {
    expect(app()->version())->not->toBeEmpty();
});
