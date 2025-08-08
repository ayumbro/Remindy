<?php

return [
    'title' => '订阅',
    'singular' => '订阅',
    'plural' => '订阅',
    'create_title' => '创建订阅',
    'edit_title' => '编辑订阅',
    'description' => '管理和跟踪您的定期付款',
    'add_subscription' => '添加订阅',
    'no_subscriptions' => '暂无订阅',
    'get_started' => '添加您的第一个订阅开始使用',
    'add_first_subscription' => '添加第一个订阅',
    'manage_track' => '管理和跟踪您的订阅',
    
    // 字段
    'fields' => [
        'name' => '名称',
        'description' => '描述',
        'price' => '价格',
        'currency' => '货币',
        'billing_cycle' => '计费周期',
        'billing_interval' => '计费间隔',
        'start_date' => '开始日期',
        'first_billing_date' => '首次计费日期',
        'next_billing_date' => '下次计费日期',
        'end_date' => '结束日期',
        'payment_method' => '支付方式',
        'categories' => '分类',
        'website_url' => '网站链接',
        'notes' => '备注',
    ],
    
    // 计费周期
    'billing_cycles' => [
        'daily' => '每日',
        'weekly' => '每周',
        'monthly' => '每月',
        'quarterly' => '每季度',
        'yearly' => '每年',
        'one-time' => '一次性',
    ],
    
    // 状态
    'status' => [
        'active' => '活跃',
        'ended' => '已结束',
        'overdue' => '已逾期',
    ],
    
    // 筛选
    'filters' => [
        'all' => '全部',
        'upcoming' => '即将到期',
    ],
    
    // 统计
    'stats' => [
        'total' => '总订阅数',
        'active' => '活跃订阅',
        'monthly_cost' => '月度费用',
        'yearly_cost' => '年度费用',
        'upcoming' => '即将到期',
        'overdue' => '已逾期',
        'due_soon' => '即将到期',
        'expired' => '已过期账单',
    ],
    
    // 消息
    'messages' => [
        'created' => '订阅创建成功',
        'updated' => '订阅更新成功',
        'deleted' => '订阅删除成功',
        'due_in_days' => ':days天后到期',
        'overdue_by_days' => '已逾期:days天',
        'bill_reminder' => '账单提醒：:name',
        'payment_due' => '付款日期：:date',
    ],
];