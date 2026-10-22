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
                'title' => 'সৌহার্দ্যপূর্ণ অভ্যর্থনা / Warm Greeting',
                'content' => "আসসালামু আলাইকুম! ফার্মলিংক সাপোর্টে আপনাকে স্বাগতম। আপনার খামার, মাছের খাদ্য বা অর্ডারের বিষয়ে আজ কীভাবে সাহায্য করতে পারি?

Hello! Thank you for reaching out to FarmLink Support. How can our aquaculture operations team assist you today?",
            ],
            [
                'scope' => 'admin',
                'shortcut' => '/hours',
                'title' => 'সাপোর্ট সময়সূচি / Support Working Hours',
                'content' => "আমাদের সাপোর্ট ডেস্ক শনি থেকে বৃহস্পতিবার সকাল ৮:০০টা থেকে রাত ৮:০০টা পর্যন্ত সক্রিয় থাকে। জরুরি খামার স্বাস্থ্য ও অক্সিজেন সংকটের জন্য আমাদের অন-কল ভেটেরিনারি টিম সার্বক্ষণিক প্রস্তুত রয়েছে।

Our support desk is active from 8:00 AM to 8:00 PM, Saturday through Thursday. For critical pond health emergencies, an on-call veterinarian will be dispatched.",
            ],
            [
                'scope' => 'admin',
                'shortcut' => '/order-status',
                'title' => 'অর্ডারের বর্তমান অবস্থা / Order Status Inquiry',
                'content' => "আপনার অর্ডারের বর্তমান অবস্থা জানতে অনুগ্রহ করে আপনার অর্ডার নম্বর (যেমন: #ORD-...) অথবা নিবন্ধিত মোবাইল নম্বরটি দিন। আমরা দ্রুত ডেলিভারির সর্বশেষ আপডেট দিচ্ছি।

To check the current dispatch status of your feed or equipment order, please share your order number or registered phone number.",
            ],
            [
                'scope' => 'admin',
                'shortcut' => '/payment-bkash',
                'title' => 'বিকাশ ও নগদ পেমেন্ট নিশ্চিতকরণ / Mobile Payment Verification',
                'content' => "বিকাশ বা নগদ-এ পেমেন্ট সম্পন্ন হলে অনুগ্রহ করে লেনদেনের ট্রানজেকশন আইডি (TrxID) এবং প্রেরকের নম্বরটি এখানে জানান। আমরা অ্যাকাউন্ট চেক করে তাৎক্ষণিক নিশ্চিত করব।

If you have completed your payment via bKash or Nagad, please reply with the Transaction ID (TrxID) and sender number for immediate confirmation.",
            ],
            [
                'scope' => 'admin',
                'shortcut' => '/delivery-time',
                'title' => 'ডেলিভারি সময়সীমা / Delivery Timeline',
                'content' => "সাধারণত অর্ডার নিশ্চিতকরণের ২৪ থেকে ৪৮ ঘণ্টার মধ্যে জেলা ও উপজেলা পর্যায়ে সরাসরি পুকুর পাড়ে পণ্য পৌঁছে দেওয়া হয়। জরুরি অক্সিজেন ও ওষুধের ক্ষেত্রে অগ্রাধিকার দেওয়া হয়।

Standard orders are dispatched and delivered to your farm within 24 to 48 hours. Emergency pond oxygen and medical supplies receive express priority dispatch.",
            ],
            [
                'scope' => 'admin',
                'shortcut' => '/damaged-feed',
                'title' => 'ক্ষতিগ্রস্ত ফিডের বস্তা প্রতিস্থাপন / Damaged Feed Replacement',
                'content' => "ফিডের কোনো বস্তা ভেজা বা ক্ষতিগ্রস্ত অবস্থায় ডেলিভারি পেলে ডেলিভারি প্রতিনিধির উপস্থিতিতে ছবি তুলে আমাদের পাঠান। আমরা বিনা খরচে অবিলম্বে তা প্রতিস্থাপন করে দেব।

If any feed bag arrives damaged or wet, please photograph it in front of the delivery agent. We will replace it immediately without any extra charge.",
            ],
            [
                'scope' => 'admin',
                'shortcut' => '/pond-visit',
                'title' => 'পুকুর ডাক্তার ভিজিট বুকিং / Schedule On-Farm Doctor Visit',
                'content' => "পুকুর ডাক্তার বা কনসালট্যান্ট ভিজিটের জন্য আপনার খামারের সঠিক ঠিকানা (উপজেলা/গ্রাম), পুকুরের আয়তন (শতাংশ/বিঘা) এবং লক্ষণসমূহ লিখে পাঠান। আমাদের টিম শিডিউল চূড়ান্ত করবে।

To schedule an on-farm doctor visit, please provide your farm location (upazila/village), water area (decimals), and symptoms observed. We will finalize your slot.",
            ],
            [
                'scope' => 'admin',
                'shortcut' => '/water-test',
                'title' => 'পুকুর পানি পরীক্ষা সেবা / Water Quality Lab Testing',
                'content' => "ফার্মলিংক ল্যাব এবং মাঠ কর্মকর্তারা পুকুরের পানির pH, দ্রবীভূত অক্সিজেন (DO), অ্যামোনিয়া ও ক্ষারত্ব পরীক্ষা করেন। আপনার পরবর্তী ভিজিটে টেস্ট করানোর জন্য সম্মতি দিন।

FarmLink provides comprehensive pond water analysis (pH, Dissolved Oxygen, Ammonia, Alkalinity). Our field officer can test your water during their next district route.",
            ],
            [
                'scope' => 'admin',
                'shortcut' => '/urgent-hotline',
                'title' => 'জরুরি হটলাইন সেবা / Emergency Aquaculture Hotline',
                'content' => "জরুরি মাছের মড়ক বা গভীর রাতে আকস্মিক অক্সিজেন ঘাটতির ক্ষেত্রে আমাদের ২৪/৭ জরুরি হটলাইনে কল করুন: 01700-000000। বিশেষজ্ঞ টিম তাৎক্ষণিক করণীয় জানিয়ে দেবে।

For critical fish mortality or late-night oxygen emergencies, please contact our 24/7 hotline directly at 01700-000000 for rapid intervention.",
            ],
            [
                'scope' => 'admin',
                'shortcut' => '/closing',
                'title' => 'সহায়তা সমাপ্তি ও শুভকামনা / Ticket Resolution & Closing',
                'content' => "ফার্মলিংক সাপোর্টের সাথে যুক্ত থাকার জন্য আন্তরিক ধন্যবাদ। আপনার খামারের উত্তরোত্তর সমৃদ্ধি কামনা করছি। যেকোনো প্রয়োজনে আমাদের আবার মেসেজ দিতে পারেন।

Thank you for choosing FarmLink. Wishing you thriving and healthy farm yields! Feel free to reach out anytime.",
            ],
        ];

        $practitionerResponses = [
            [
                'scope' => 'practitioner',
                'shortcut' => '/water-check',
                'title' => 'সকালের পানির গুণাগুণ পরীক্ষা / Morning Parameter Check',
                'content' => "অনুগ্রহ করে সূর্যোদয়ের আগে সকালে আপনার পুকুরের pH এবং দ্রবীভূত অক্সিজেন (DO) পরিমাপ করুন। যদি অক্সিজেন ৪.০ mg/L এর নিচে থাকে, অবিলম্বে অ্যারোটর চালু করুন এবং খাবার দেওয়া স্থগিত রাখুন।

Please measure your pond's pH and dissolved oxygen early in the morning before sunrise. If DO is below 4.0 mg/L, turn on your aerator immediately and suspend feeding.",
            ],
            [
                'scope' => 'practitioner',
                'shortcut' => '/feed-reduce',
                'title' => 'খাদ্য সরবরাহ হ্রাসকরণ / Reduce Feed Dosage',
                'content' => "পানির বর্তমান তাপমাত্রা ও অ্যামোনিয়ার মাত্রার কারণে মাছের চাপ কমাতে এবং পানি নষ্ট হওয়া রোধে আগামী ৪৮ ঘণ্টার জন্য দৈনিক খাবারের পরিমাণ ৫০% কমিয়ে দিন।

Due to current water temperature and ammonia levels, please reduce daily feed ration by 50% for the next 48 hours to avoid stress and water fouling.",
            ],
            [
                'scope' => 'practitioner',
                'shortcut' => '/medication-mix',
                'title' => 'খাবারের সাথে ওষুধ মেশানো / Medication Pelleting Instructions',
                'content' => "প্রেসক্রিপশন অনুযায়ী ওষুধ প্রতি কেজি খাবারের জন্য পরিমাপ করুন। বাইন্ডার হিসেবে প্রতি কেজি খাবারে ২০ মিলি সয়াবিন তেল বা চিটাগুড় মিশিয়ে ছায়াযুক্ত স্থানে ২০ মিনিট শুকিয়ে পুকুরে ছিটান। রোদে শুকাবেন না।

Mix the prescribed medicine with commercial feed using vegetable oil or molasses as a binder (20ml per kg feed). Let it shade-dry for 20 minutes before broadcasting. Do not sun-dry.",
            ],
            [
                'scope' => 'practitioner',
                'shortcut' => '/symptoms-check',
                'title' => 'মাছের লক্ষণ ও আচরণ পরীক্ষা / Fish Behavior & Symptoms Check',
                'content' => "মাছ কি অস্বাভাবিকভাবে সাঁতার কাটছে, পানির খাঁড়িতে জড়ো হচ্ছে, অথবা পাখনা ও পেটে লাল ঘা/ক্ষত দেখা যাচ্ছে? সম্ভব হলে আক্রান্ত মাছের পরিষ্কার ছবি বা ভিডিও পাঠান।

Are the fish swimming erratically, gathering near the inlet, or showing red ulcerations on the fins or belly? Please send a clear photo or video if possible.",
            ],
            [
                'scope' => 'practitioner',
                'shortcut' => '/liming',
                'title' => 'কৃষি চুন বা ডলোমাইট প্রয়োগ / Agricultural Lime Application',
                'content' => "পানির অম্লতা দূর করতে প্রতি শতকে ১ থেকে ২ কেজি কৃষি চুন বা ডলোমাইট পানিতে গুলে ঠান্ডা করে রোদের সময় পুকুরের উপরিভাগে সমানভাবে ছিটিয়ে দিন।

Apply quicklime (calcium oxide) or agricultural lime at 1 to 2 kg per decimal, dissolved in water and spread evenly across the pond surface during sunny hours.",
            ],
            [
                'scope' => 'practitioner',
                'shortcut' => '/oxygen-alert',
                'title' => 'জরুরি অক্সিজেন ঘাটতি ও প্রতিকার / Critical Oxygen Emergency',
                'content' => "জরুরি সতর্কতা: ভোরে মাছ ভেসে উঠলে বা অক্সিজেন ৪.০ mg/L এর নিচে নামলে অবিলম্বে সব খাবার বন্ধ করুন। অ্যারোটর চালু করুন এবং প্রতি শতকে ১০০-১৫০ গ্রাম অক্সিজেন ট্যাবলেট (Oxy-Max/Oxy-Flow) দ্রুত ছিটান।

CRITICAL: If fish are gasping at the surface or DO < 4.0 mg/L, stop feeding immediately. Turn on aerators and apply oxygen tablets at 100-150g per decimal.",
            ],
            [
                'scope' => 'practitioner',
                'shortcut' => '/ammonia-control',
                'title' => 'বিষাক্ত অ্যামোনিয়া নিয়ন্ত্রণ / Toxic Ammonia Spike Protocol',
                'content' => "অ্যামোনিয়া কমাতে আগামী ৪৮ ঘণ্টার জন্য খাদ্য সরবরাহ ৫০% হ্রাস করুন। তলদেশের বিষাক্ত গ্যাস দূর করতে প্রতি শতকে ২৫০ গ্রাম জিওলাইট এবং প্রতি শতকে ১০০ গ্রাম চিটাগুড় প্রোবায়োটিকের সাথে প্রয়োগ করুন।

To control high ammonia, reduce daily feeding by 50% for 48 hours. Apply zeolite at 250g/decimal and molasses (100g/decimal) mixed with active probiotics.",
            ],
            [
                'scope' => 'practitioner',
                'shortcut' => '/cloudy-weather',
                'title' => 'মেঘলা ও বৃষ্টির দিনে সতর্কতা / Rainy & Overcast Feeding',
                'content' => "মেঘলা আকাশ, অতিরিক্ত বৃষ্টি বা গুমোট আবহাওয়ায় প্রাকৃতিক আলো ও অক্সিজেনের ঘাটতি থাকে। এই সময়ে স্বাভাবিক খাবারের ৩০-৫০% কমিয়ে দিন, অন্যথায় অপচিত খাবার পচে পানি নষ্ট হবে।

During overcast or rainy days, photosynthesis halts and DO drops. Cut feed rations by 30-50% to prevent unconsumed pellets from polluting the pond bottom.",
            ],
            [
                'scope' => 'practitioner',
                'shortcut' => '/ulcer-disease',
                'title' => 'মাছের ঘা ও লেজ পচা রোগ / Fin Rot & Red Spot Treatment',
                'content' => "মাছের গায়ে লাল দাগ, ঘা বা পাখনায় রক্তক্ষরণ দেখা দিলে প্রতি শতকে ১ থেকে ২ গ্রাম পটাশিয়াম পারম্যাঙ্গানেট (KMnO4) পানিতে গুলে পুরো পুকুরে ছিটান। তীব্রতায় প্রতি শতকে ২৫০ গ্রাম খাবার লবণ ব্যবহার করুন।

For body ulcers or fin rot, dissolve and apply Potassium Permanganate (KMnO4) at 1-2g per decimal across the pond. In severe cases, add 250g non-iodized salt per decimal.",
            ],
            [
                'scope' => 'practitioner',
                'shortcut' => '/probiotics-brew',
                'title' => 'প্রোবায়োটিক ও চিটাগুড় গাঁজন / Probiotic Fermentation Protocol',
                'content' => "পুকুরের স্বাস্থ্য ভালো রাখতে প্রোবায়োটিক প্রয়োগের নিয়ম: প্রতি শতকের জন্য নির্দেশিত প্রোবায়োটিক, ২০০ গ্রাম চিটাগুড় ও ৫ লিটার পুকুরের পানিতে মিশিয়ে এয়ারেশন দিয়ে ২৪ ঘণ্টা ঢেকে রেখে রোদের সময় ছিটিয়ে দিন।

To activate probiotics: mix recommended dose with 200g molasses and 5L pond water per decimal, aerate/cover for 24 hours, and broadcast evenly during sunny morning hours.",
            ],
            [
                'scope' => 'practitioner',
                'shortcut' => '/fry-stocking',
                'title' => 'পোনা ছাড়ার নিয়ম ও জীবাণুমুক্তকরণ / Fingerling Acclimatization',
                'content' => "পোনা ছাড়ার আগে ব্যাগটি পুকুরের পানিতে ২০-৩০ মিনিট ভাসিয়ে তাপমাত্রা মিলিয়ে নিন। ছাড়ার পূর্বে ১% লবণ পানিতে বা হালকা পটাশ মিশ্রিত পানিতে পোনাকে ৩০-৬০ সেকেন্ড গোসল করিয়ে ধীরে ধীরে পুকুরে ছাড়ুন।

Float fingerling bags in pond water for 20-30 minutes to equalize temperature. Dip bath fry in a 1% salt or mild KMnO4 solution for 30-60 seconds before releasing.",
            ],
            [
                'scope' => 'practitioner',
                'shortcut' => '/clinical-followup',
                'title' => 'চিকিৎসাত্তোর খামার পর্যবেক্ষণ / Clinical Recovery Follow-Up',
                'content' => "চিকিৎসা শুরুর ৩ দিন পর মাছের খাদ্য গ্রহণের প্রবণতা এবং সাঁতারের আচরণ কেমন তা আমাদের জানান। প্রয়োজনে পানির বর্তমান pH ও অক্সিজেনের রিডিং এবং স্পষ্ট ছবি শেয়ার করুন।

Please report back 3 days after starting treatment: observe fish appetite, swimming behavior, and provide updated pH/DO readings along with photos if symptoms persist.",
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
