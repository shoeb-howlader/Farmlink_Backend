<?php

namespace Database\Seeders;

use App\Models\AdminNotification;
use App\Models\Order;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    /**
     * Seed realistic notifications across all primary demo roles.
     */
    public function run(): void
    {
        $admin = User::where('email', 'admin@farmlink.com')->first();
        $deo = User::where('email', 'deo@farmlink.com')->first();
        $vet = User::where('email', 'vet@farmlink.com')->first();
        $consultant = User::where('email', 'consultant@farmlink.com')->first();
        $farmer = User::where('email', 'farmer@farmlink.com')->first();

        // ══════════════════════════════════════════════════════════════════
        // 1. ADMIN NOTIFICATIONS (global user_id = null and admin user_id)
        // ══════════════════════════════════════════════════════════════════
        $adminNotifications = [
            [
                'user_id' => null,
                'type' => 'service_request.urgent',
                'title' => 'Urgent Service Request #SR-102',
                'message' => 'Farmer Abdul Karim submitted urgent veterinary request for Pond #2 at Karim Shrimp Farm.',
                'data' => ['request_id' => 1, 'farm_name' => 'Karim Shrimp Farm', 'urgency' => 'urgent'],
                'read_at' => null,
                'created_at' => now()->subHours(2),
            ],
            [
                'user_id' => null,
                'type' => 'farmer.pending_approval',
                'title' => 'New Farmer Awaiting Approval',
                'message' => 'New farmer awaiting approval: Hasanuzzaman Molla, 01987654321, Satkhira',
                'data' => ['farmer_id' => 2, 'phone' => '01987654321', 'district' => 'Satkhira'],
                'read_at' => null,
                'created_at' => now()->subHours(4),
            ],
            [
                'user_id' => null,
                'type' => 'order.pos_sale',
                'title' => 'Assisted POS Sale Completed',
                'message' => 'Assisted counter sale of ৳ 2,450.00 processed by DEO Officer at Satkhira Hub.',
                'data' => ['order_id' => 1, 'total' => 2450.00, 'channel' => 'admin_pos'],
                'read_at' => null,
                'created_at' => now()->subHours(7),
            ],
            [
                'user_id' => null,
                'type' => 'stock',
                'title' => 'Low Stock Alert: OxyFlow Pond Aerator 2HP',
                'message' => 'Inventory has fallen to 5 units, below the minimum threshold of 10.',
                'data' => ['product_id' => 3, 'current_stock' => 5, 'min_threshold' => 10],
                'read_at' => now()->subHours(18),
                'created_at' => now()->subDay(),
            ],
            [
                'user_id' => null,
                'type' => 'order',
                'title' => 'New Order Received #ORD-4029',
                'message' => 'Customer placed an online order for feeds & probiotics totaling ৳ 4,900.00.',
                'data' => ['order_id' => 2, 'total' => 4900.00, 'channel' => 'web'],
                'read_at' => now()->subDays(2),
                'created_at' => now()->subDays(2),
            ],
            [
                'user_id' => null,
                'type' => 'service_request.completed',
                'title' => 'Veterinary Visit Fulfilled',
                'message' => 'Dr. Rafiqul Islam completed clinical diagnosis and submitted treatment logs for Karim Shrimp Farm.',
                'data' => ['request_id' => 3, 'vet_id' => $vet?->id],
                'read_at' => now()->subDays(3),
                'created_at' => now()->subDays(3),
            ],
            [
                'user_id' => $admin?->id,
                'type' => 'security',
                'title' => 'Security Audit Trail Logged',
                'message' => 'Database automated backup and role permission checks completed successfully.',
                'data' => ['status' => 'success'],
                'read_at' => now()->subDays(4),
                'created_at' => now()->subDays(4),
            ],
            [
                'user_id' => null,
                'type' => 'stock',
                'title' => 'Low Stock Alert: VitaBoost Growth Promoter 1kg',
                'message' => 'Inventory has fallen to 8 units, below the minimum threshold of 15.',
                'data' => ['product_id' => 5, 'current_stock' => 8, 'min_threshold' => 15],
                'read_at' => now()->subDays(5),
                'created_at' => now()->subDays(5),
            ],
            [
                'user_id' => null,
                'type' => 'order',
                'title' => 'New Bulk Order Received #ORD-4035',
                'message' => 'Customer placed an online feed order totaling ৳ 18,500.00 awaiting dispatch.',
                'data' => ['order_id' => 3, 'total' => 18500.00, 'channel' => 'web'],
                'read_at' => now()->subDays(6),
                'created_at' => now()->subDays(6),
            ],
            [
                'user_id' => null,
                'type' => 'farmer',
                'title' => 'Farmer Profile Updated #FARM-201',
                'message' => 'Farmer Abdul Karim updated pond culture parameters and water exchange logs.',
                'data' => ['farmer_id' => 1, 'farm_id' => 1],
                'read_at' => now()->subDays(7),
                'created_at' => now()->subDays(7),
            ],
        ];

        foreach ($adminNotifications as $n) {
            AdminNotification::create($n);
        }

        // ══════════════════════════════════════════════════════════════════
        // 2. DEMO FARMER NOTIFICATIONS (Abdul Karim)
        // ══════════════════════════════════════════════════════════════════
        if ($farmer) {
            $farmerNotifications = [
                [
                    'user_id' => $farmer->id,
                    'type' => 'order.updated',
                    'title' => 'Order Items Modified #ORD-3012',
                    'message' => 'Your order supplies were modified by administrative staff. Recalculated total: ৳ 7,350.00.',
                    'data' => ['order_id' => 1, 'invoice_number' => 'ORD-3012', 'new_total' => 7350.00],
                    'read_at' => null,
                    'created_at' => now()->subHours(1),
                ],
                [
                    'user_id' => $farmer->id,
                    'type' => 'service_request.assigned',
                    'title' => 'Specialist Assigned to Your Request',
                    'message' => 'Md. Hasan Ali (Consultant) has been assigned to your water quality and stocking density request.',
                    'data' => ['service_request_id' => 2, 'specialist_name' => 'Md. Hasan Ali'],
                    'read_at' => null,
                    'created_at' => now()->subHours(5),
                ],
                [
                    'user_id' => $farmer->id,
                    'type' => 'order.dispatched',
                    'title' => 'Supplies Dispatched for Delivery',
                    'message' => 'Your order #ORD-3012 has been packed and handed over to Farmlink regional logistics van.',
                    'data' => ['order_id' => 1, 'tracking_code' => 'FL-VAN-88'],
                    'read_at' => null,
                    'created_at' => now()->subHours(14),
                ],
                [
                    'user_id' => $farmer->id,
                    'type' => 'service_request.completed',
                    'title' => 'Veterinary Prescription Available',
                    'message' => 'Dr. Rafiqul Islam recorded diagnosis and prescribed Oxytetracycline 20% treatment protocol.',
                    'data' => ['service_request_id' => 3, 'farm_name' => 'Karim Shrimp Farm'],
                    'read_at' => now()->subDay(),
                    'created_at' => now()->subDays(2),
                ],
                [
                    'user_id' => $farmer->id,
                    'type' => 'order.confirmed',
                    'title' => 'Order Confirmed #ORD-3012',
                    'message' => 'Payment confirmed for order #ORD-3012. Processing at Khulna depot.',
                    'data' => ['order_id' => 1, 'total' => 4900.00],
                    'read_at' => now()->subDays(3),
                    'created_at' => now()->subDays(3),
                ],
                [
                    'user_id' => $farmer->id,
                    'type' => 'order.delivered',
                    'title' => 'Order Delivered Successfully',
                    'message' => 'Your feed order #ORD-2094 was delivered to Karim Shrimp Farm. Thank you for choosing FarmLink!',
                    'data' => ['order_id' => 3],
                    'read_at' => now()->subDays(5),
                    'created_at' => now()->subDays(5),
                ],
            ];

            foreach ($farmerNotifications as $n) {
                AdminNotification::create($n);
            }
        }

        // ══════════════════════════════════════════════════════════════════
        // 3. VETERINARY PRACTITIONER NOTIFICATIONS (Dr. Rafiqul Islam)
        // ══════════════════════════════════════════════════════════════════
        if ($vet) {
            $vetNotifications = [
                [
                    'user_id' => $vet->id,
                    'type' => 'service_request.assigned',
                    'title' => 'Urgent Field Request Assigned #SR-102',
                    'message' => 'You have been assigned to an urgent case: White spot disease symptoms in Pond #2 at Karim Shrimp Farm.',
                    'data' => ['service_request_id' => 1, 'urgency' => 'urgent', 'farmer_name' => 'Abdul Karim'],
                    'read_at' => null,
                    'created_at' => now()->subHours(3),
                ],
                [
                    'user_id' => $vet->id,
                    'type' => 'service_request.feedback',
                    'title' => '5-Star Feedback Received',
                    'message' => 'Farmer Abdul Karim gave you a 5-star rating: "Dr. Rafiqul responded very quickly and gill necrosis resolved."',
                    'data' => ['service_request_id' => 3, 'rating' => 5],
                    'read_at' => null,
                    'created_at' => now()->subHours(16),
                ],
                [
                    'user_id' => $vet->id,
                    'type' => 'service_request.completed',
                    'title' => 'Clinical Case Finalized',
                    'message' => 'Clinical record #VR-401 for bacterial gill necrosis closed and synchronized with farm dossier.',
                    'data' => ['service_request_id' => 3],
                    'read_at' => now()->subDays(2),
                    'created_at' => now()->subDays(2),
                ],
            ];

            foreach ($vetNotifications as $n) {
                AdminNotification::create($n);
            }
        }

        // ══════════════════════════════════════════════════════════════════
        // 4. CONSULTANT NOTIFICATIONS (Md. Hasan Ali)
        // ══════════════════════════════════════════════════════════════════
        if ($consultant) {
            $consultantNotifications = [
                [
                    'user_id' => $consultant->id,
                    'type' => 'service_request.assigned',
                    'title' => 'New Advisory Request Assigned',
                    'message' => 'Advisory requested for post-larvae stocking density and natural pond feed management at Karim Shrimp Farm.',
                    'data' => ['service_request_id' => 2, 'farmer_phone' => '01712345678'],
                    'read_at' => null,
                    'created_at' => now()->subHours(6),
                ],
                [
                    'user_id' => $consultant->id,
                    'type' => 'service_request.feedback',
                    'title' => 'Client Evaluation Received',
                    'message' => 'Farmer completed post-visit advisory review and rated your guidance 5 stars.',
                    'data' => ['rating' => 5],
                    'read_at' => now()->subDays(1),
                    'created_at' => now()->subDays(2),
                ],
            ];

            foreach ($consultantNotifications as $n) {
                AdminNotification::create($n);
            }
        }

        // ══════════════════════════════════════════════════════════════════
        // 5. DATA ENTRY OPERATOR NOTIFICATIONS (DEO Officer)
        // ══════════════════════════════════════════════════════════════════
        if ($deo) {
            $deoNotifications = [
                [
                    'user_id' => $deo->id,
                    'type' => 'farmer',
                    'title' => 'Farmer Registration Confirmed',
                    'message' => 'Hasanuzzaman Molla successfully registered and phone identifier verified in Satkhira.',
                    'data' => ['farmer_id' => 2],
                    'read_at' => null,
                    'created_at' => now()->subHours(4),
                ],
                [
                    'user_id' => $deo->id,
                    'type' => 'order.pos_sale',
                    'title' => 'Receipt Generated for Assisted Sale',
                    'message' => 'Assisted POS order #POS-1002 of ৳ 2,450.00 registered and receipt sent to farmer.',
                    'data' => ['order_id' => 1, 'total' => 2450.00],
                    'read_at' => now()->subDay(),
                    'created_at' => now()->subDays(2),
                ],
            ];

            foreach ($deoNotifications as $n) {
                AdminNotification::create($n);
            }
        }
    }
}
