<?php

test('the example endpoint is intentionally absent from the target system', function (): void {
    $this->get('/example')->assertNotFound();
});
