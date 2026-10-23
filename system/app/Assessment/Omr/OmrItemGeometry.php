<?php

namespace App\Assessment\Omr;

/**
 * One answerable row on the sheet: pinned geometry for a single item and its
 * options. The answer key stays inside NEXIAM and never crosses the provider
 * boundary. Providers key results by stable area code, never database ids.
 */
final readonly class OmrItemGeometry
{
    /**
     * @param  list<OmrOptionGeometry>  $options
     */
    public function __construct(
        public int $position,
        public string $areaCode,
        public int $questionNo,
        public string $nativeLabel,
        public OmrRegion $region,
        public array $options,
        public int $pageNo = 1,
        public ?string $physicalItemUid = null,
    ) {}

    /**
     * @return list<string>
     */
    public function optionCodes(): array
    {
        return array_map(fn (OmrOptionGeometry $option): string => $option->code, $this->options);
    }
}
