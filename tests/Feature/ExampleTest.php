<?php

test('the application is bootable', function () {
    $this->assertNotNull(app()->environment());
});
