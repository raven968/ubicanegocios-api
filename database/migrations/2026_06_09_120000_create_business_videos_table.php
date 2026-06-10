<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('url');
            $table->string('orientation', 16)->default('horizontal');
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });

        // Migrate the existing single video into the new table.
        DB::table('businesses')
            ->whereNotNull('video_url')
            ->where('video_url', '!=', '')
            ->orderBy('id')
            ->get(['id', 'video_url', 'video_orientation'])
            ->each(function ($b) {
                DB::table('business_videos')->insert([
                    'business_id' => $b->id,
                    'url' => $b->video_url,
                    'orientation' => $b->video_orientation ?? 'horizontal',
                    'order' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['video_url', 'video_orientation']);
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('video_url')->nullable();
            $table->string('video_orientation', 16)->default('horizontal');
        });

        // Restore the first video back onto the business.
        DB::table('business_videos')
            ->orderBy('business_id')
            ->orderBy('order')
            ->get()
            ->groupBy('business_id')
            ->each(function ($videos, $businessId) {
                $first = $videos->first();
                DB::table('businesses')->where('id', $businessId)->update([
                    'video_url' => $first->url,
                    'video_orientation' => $first->orientation,
                ]);
            });

        Schema::dropIfExists('business_videos');
    }
};
