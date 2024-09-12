<?php

if (!function_exists('array_any')) :
    function array_any(array $array, callable $predicate): bool {
        foreach ($array as $value) {
            if ($predicate($value)) {
                return true;
            }
        }
        return false;
    }

endif;
