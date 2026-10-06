<?php

namespace App\Traits;

use App\Models\District;
use App\Models\Pourashava;
use App\Models\Union;
use App\Models\Upazila;

trait SyncsLocationData
{
    /**
     * Synchronize foreign keys and fallback text columns.
     */
    protected function syncLocationData(array $data): array
    {
        if (!empty($data['district_id'])) {
            $district = District::find($data['district_id']);
            if ($district) {
                $data['division_id'] = $district->division_id;
                if (empty($data['district'])) {
                    $data['district'] = $district->name;
                }
            }
        }

        if (!empty($data['upazila_id'])) {
            $upazila = Upazila::find($data['upazila_id']);
            if ($upazila && empty($data['upazila'])) {
                $data['upazila'] = $upazila->name;
            }
        }

        if (!empty($data['union_id'])) {
            $union = Union::find($data['union_id']);
            if ($union && empty($data['union'])) {
                $data['union'] = $union->name;
            }
        } elseif (!empty($data['pourashava_id'])) {
            $pourashava = Pourashava::find($data['pourashava_id']);
            if ($pourashava && empty($data['union'])) {
                $data['union'] = $pourashava->name;
            }
        }

        return $data;
    }
}
