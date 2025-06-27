<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration creates the 'role' span type with comprehensive
     * subtypes and specific role options for tracking professional,
     * academic, and other roles that people hold over time.
     */
    public function up(): void
    {
        // Define the role span type with subtypes and comprehensive role options
        $roleSpanType = [
            'type_id' => 'role',
            'name' => 'Role',
            'description' => 'A role, position, or function that a person holds during a specific time period',
            'metadata' => [
                'schema' => [
                    'subtype' => [
                        'help' => 'Category of role',
                        'type' => 'select',
                        'label' => 'Role Category',
                        'options' => [
                            'academic', 'professional', 'creative', 'political', 'legal',
                            'military', 'religious', 'medical', 'business', 'media',
                            'sports', 'family', 'status', 'other'
                        ],
                        'required' => true,
                        'component' => 'select'
                    ],
                    'specific_role' => [
                        'help' => 'Specific role or position title',
                        'type' => 'select',
                        'label' => 'Specific Role',
                        'options' => [
                            // Academic & Education
                            'scientist', 'researcher', 'mathematician', 'philosopher', 'academic',
                            'educator', 'student', 'historian', 'librarian', 'pupil',
                            
                            // Creative & Arts
                            'artist', 'writer', 'poet', 'playwright', 'musician', 'composer',
                            'actor', 'filmmaker', 'designer', 'photographer', 'architect',
                            
                            // Political & Government
                            'politician', 'monarch', 'civil servant', 'activist', 'diplomat',
                            
                            // Legal & Justice
                            'judge', 'lawyer', 'police officer',
                            
                            // Professional & Technical
                            'engineer', 'product manager', 'technician', 'developer', 'consultant',
                            
                            // Business & Commerce
                            'entrepreneur', 'inventor', 'businessperson', 'craftsman', 'farmer',
                            'merchant', 'investor', 'banker', 'trader', 'industrialist',
                            
                            // Media & Communication
                            'journalist', 'broadcaster', 'editor', 'influencer', 'public intellectual',
                            
                            // Military & Security
                            'soldier', 'officer', 'spy', 'general',
                            
                            // Religious & Spiritual
                            'priest', 'monk', 'nun', 'rabbi', 'imam', 'guru',
                            
                            // Sports & Competition
                            'athlete', 'coach', 'referee', 'competitor', 'sports official',
                            
                            // Medical & Healthcare
                            'doctor', 'nurse', 'midwife', 'therapist', 'carer',
                            
                            // Status & Circumstances
                            'refugee', 'prisoner', 'explorer', 'martyr', 'founder', 'pioneer',
                            'retiree', 'freelance',
                            
                            // Family & Personal
                            'parent',
                            
                            // Other
                            'other'
                        ],
                        'required' => true,
                        'component' => 'select'
                    ],
                    'organisation' => [
                        'help' => 'Organisation where this role is held (if applicable)',
                        'type' => 'span',
                        'label' => 'Organisation',
                        'required' => false,
                        'component' => 'span-input',
                        'span_type' => 'organisation'
                    ],
                    'description' => [
                        'help' => 'Additional details about this specific role',
                        'type' => 'textarea',
                        'label' => 'Role Description',
                        'required' => false,
                        'component' => 'textarea'
                    ]
                ]
            ]
        ];

        // Insert the role span type
        DB::table('span_types')->insert([
            'type_id' => $roleSpanType['type_id'],
            'name' => $roleSpanType['name'],
            'description' => $roleSpanType['description'],
            'metadata' => json_encode($roleSpanType['metadata']),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    /**
     * Reverse the migrations.
     * 
     * Remove the role span type.
     */
    public function down(): void
    {
        // Remove the role span type
        DB::table('span_types')->where('type_id', 'role')->delete();
    }
};
