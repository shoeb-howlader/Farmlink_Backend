<?php

namespace Database\Seeders;

use App\Models\CannedResponse;
use Illuminate\Database\Seeder;

class CannedResponseSeeder extends Seeder
{
    public function run(): void
    {
        $adminResponses = [
            [
                'scope' => 'admin',
                'shortcut' => '/greeting',
                'title' => 'Warm Greeting & Assistance',
                'content' => "Hello! Thank you for reaching out to FarmLink Support. How can our aquaculture team assist you today?",
            ],
            [
                'scope' => 'admin',
                'shortcut' => '/hours',
                'title' => 'Support Working Hours',
                'content' => "Our support desk is active from 8:00 AM to 8:00 PM, Saturday through Thursday. For critical pond health emergencies, an on-call veterinarian will be dispatched.",
            ],
            [
                'scope' => 'admin',
                'shortcut' => '/order-status',
                'title' => 'Order & Delivery Inquiry',
                'content' => "To check the current dispatch status of your feed or equipment order, please share your order number or registered phone number.",
            ],
            [
                'scope' => 'admin',
                'shortcut' => '/pond-visit',
                'title' => 'Schedule On-Farm Pond Doctor',
                'content' => "To schedule a pond doctor visit, please provide your farm location, pond surface area (in decimals/bighas), and describe any symptoms observed in your stock.",
            ],
            [
                'scope' => 'admin',
                'shortcut' => '/water-test',
                'title' => 'Water Test Kit & Lab Service',
                'content' => "FarmLink provides comprehensive pond water analysis (pH, Dissolved Oxygen, Ammonia, Alkalinity). Our field officer can test your water during their next district route.",
            ],
        ];

        $practitionerResponses = [
            [
                'scope' => 'practitioner',
                'shortcut' => '/water-check',
                'title' => 'Morning Parameter Check',
                'content' => "Please measure your pond's pH and dissolved oxygen early in the morning before sunrise. If DO is below 4.0 mg/L, turn on your aerator immediately and suspend feeding.",
            ],
            [
                'scope' => 'practitioner',
                'shortcut' => '/feed-reduce',
                'title' => 'Reduce Feed Dosage',
                'content' => "Due to current water temperature and ammonia levels, please reduce daily feed ration by 50% for the next 48 hours to avoid stress and water fouling.",
            ],
            [
                'scope' => 'practitioner',
                'shortcut' => '/medication-mix',
                'title' => 'Medication Pelleting Instructions',
                'content' => "Mix the prescribed medicine with commercial feed using vegetable oil or molasses as a binder (20ml per kg feed). Let it shade-dry for 20 minutes before broadcasting.",
            ],
            [
                'scope' => 'practitioner',
                'shortcut' => '/symptoms-check',
                'title' => 'Fish Behavior & Symptoms Check',
                'content' => "Are the fish swimming erratically, gathering near the inlet, or showing red ulcerations on the fins or belly? Please send a clear photo or video if possible.",
            ],
            [
                'scope' => 'practitioner',
                'shortcut' => '/liming',
                'title' => 'Agricultural Lime Application',
                'content' => "Apply quicklime (calcium oxide) or agricultural lime at 1 to 2 kg per decimal, dissolved in water and spread evenly across the pond surface during sunny hours.",
            ],
        ];

        foreach (array_merge($adminResponses, $practitionerResponses) as $item) {
            CannedResponse::updateOrCreate(
                ['scope' => $item['scope'], 'shortcut' => $item['shortcut']],
                [
                    'title' => $item['title'],
                    'content' => $item['content'],
                    'is_active' => true,
                ]
            );
        }
    }
}
