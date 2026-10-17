<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Api\Data;

interface OrderProductInterface
{
    /** @return string */
    public function getSource(): string;
    /** @return string */
    public function getReference(): string;
    /** @return string */
    public function getName(): string;
    /** @return string */
    public function getSku(): string;
}
