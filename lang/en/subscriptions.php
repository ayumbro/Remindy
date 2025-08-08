<?php

return [
    'title' => 'Subscriptions',
    'singular' => 'Subscription',
    'plural' => 'Subscriptions',
    'create_title' => 'Create Subscription',
    'edit_title' => 'Edit Subscription',
    'description' => 'Manage and track your recurring payments',
    'add_subscription' => 'Add Subscription',
    'no_subscriptions' => 'No subscriptions yet',
    'get_started' => 'Get started by adding your first subscription',
    'add_first_subscription' => 'Add First Subscription',
    'manage_track' => 'Manage and track your subscriptions',
    
    // Fields
    'fields' => [
        'name' => 'Name',
        'description' => 'Description',
        'price' => 'Price',
        'currency' => 'Currency',
        'billing_cycle' => 'Billing Cycle',
        'billing_interval' => 'Billing Interval',
        'start_date' => 'Start Date',
        'first_billing_date' => 'First Billing Date',
        'next_billing_date' => 'Next Billing Date',
        'end_date' => 'End Date',
        'payment_method' => 'Payment Method',
        'categories' => 'Categories',
        'website_url' => 'Website URL',
        'notes' => 'Notes',
    ],
    
    // Billing cycles
    'billing_cycles' => [
        'daily' => 'Daily',
        'weekly' => 'Weekly',
        'monthly' => 'Monthly',
        'quarterly' => 'Quarterly',
        'yearly' => 'Yearly',
        'one-time' => 'One-time',
    ],
    
    // Status
    'status' => [
        'active' => 'Active',
        'ended' => 'Ended',
        'overdue' => 'Overdue',
    ],
    
    // Filters
    'filters' => [
        'all' => 'All',
        'upcoming' => 'Upcoming',
    ],
    
    // Statistics
    'stats' => [
        'total' => 'Total Subscriptions',
        'active' => 'Active Subscriptions',
        'monthly_cost' => 'Monthly Cost',
        'yearly_cost' => 'Yearly Cost',
        'upcoming' => 'Upcoming Payments',
        'overdue' => 'Overdue Payments',
        'due_soon' => 'Due Soon',
        'expired' => 'Expired Bills',
    ],
    
    // Messages
    'messages' => [
        'created' => 'Subscription created successfully',
        'updated' => 'Subscription updated successfully',
        'deleted' => 'Subscription deleted successfully',
        'due_in_days' => 'Due in :days day|Due in :days days',
        'overdue_by_days' => 'Overdue by :days day|Overdue by :days days',
        'bill_reminder' => 'Bill Reminder: :name',
        'payment_due' => 'Payment due on :date',
    ],
];