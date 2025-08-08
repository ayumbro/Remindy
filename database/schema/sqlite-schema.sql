CREATE TABLE IF NOT EXISTS "migrations"(
  "id" integer primary key autoincrement not null,
  "migration" varchar not null,
  "batch" integer not null
);
CREATE TABLE IF NOT EXISTS "password_reset_tokens"(
  "email" varchar not null,
  "token" varchar not null,
  "created_at" datetime,
  primary key("email")
);
CREATE TABLE IF NOT EXISTS "sessions"(
  "id" varchar not null,
  "user_id" integer,
  "ip_address" varchar,
  "user_agent" text,
  "payload" text not null,
  "last_activity" integer not null,
  primary key("id")
);
CREATE INDEX "sessions_user_id_index" on "sessions"("user_id");
CREATE INDEX "sessions_last_activity_index" on "sessions"("last_activity");
CREATE TABLE IF NOT EXISTS "cache"(
  "key" varchar not null,
  "value" text not null,
  "expiration" integer not null,
  primary key("key")
);
CREATE TABLE IF NOT EXISTS "cache_locks"(
  "key" varchar not null,
  "owner" varchar not null,
  "expiration" integer not null,
  primary key("key")
);
CREATE TABLE IF NOT EXISTS "jobs"(
  "id" integer primary key autoincrement not null,
  "queue" varchar not null,
  "payload" text not null,
  "attempts" integer not null,
  "reserved_at" integer,
  "available_at" integer not null,
  "created_at" integer not null
);
CREATE INDEX "jobs_queue_index" on "jobs"("queue");
CREATE TABLE IF NOT EXISTS "job_batches"(
  "id" varchar not null,
  "name" varchar not null,
  "total_jobs" integer not null,
  "pending_jobs" integer not null,
  "failed_jobs" integer not null,
  "failed_job_ids" text not null,
  "options" text,
  "cancelled_at" integer,
  "created_at" integer not null,
  "finished_at" integer,
  primary key("id")
);
CREATE TABLE IF NOT EXISTS "failed_jobs"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar not null,
  "connection" text not null,
  "queue" text not null,
  "payload" text not null,
  "exception" text not null,
  "failed_at" datetime not null default CURRENT_TIMESTAMP
);
CREATE UNIQUE INDEX "failed_jobs_uuid_unique" on "failed_jobs"("uuid");
CREATE TABLE IF NOT EXISTS "categories"(
  "id" integer primary key autoincrement not null,
  "user_id" integer not null,
  "name" varchar not null,
  "color" varchar,
  "description" text,
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("user_id") references "users"("id") on delete cascade
);
CREATE INDEX "categories_user_id_is_active_index" on "categories"(
  "user_id",
  "is_active"
);
CREATE TABLE IF NOT EXISTS "payment_methods"(
  "id" integer primary key autoincrement not null,
  "user_id" integer not null,
  "name" varchar not null,
  "description" text,
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  "image_path" varchar,
  foreign key("user_id") references "users"("id") on delete cascade
);
CREATE INDEX "payment_methods_user_id_is_active_index" on "payment_methods"(
  "user_id",
  "is_active"
);
CREATE TABLE IF NOT EXISTS "payment_histories"(
  "id" integer primary key autoincrement not null,
  "subscription_id" integer not null,
  "amount" numeric not null,
  "currency_id" integer not null,
  "payment_method_id" integer,
  "payment_date" date not null,
  "status" varchar check("status" in('paid', 'pending', 'failed', 'refunded')) not null default 'paid',
  "notes" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("subscription_id") references "subscriptions"("id") on delete cascade,
  foreign key("currency_id") references "currencies"("id"),
  foreign key("payment_method_id") references "payment_methods"("id") on delete set null
);
CREATE INDEX "payment_histories_subscription_id_payment_date_index" on "payment_histories"(
  "subscription_id",
  "payment_date"
);
CREATE INDEX "payment_histories_payment_date_status_index" on "payment_histories"(
  "payment_date",
  "status"
);
CREATE TABLE IF NOT EXISTS "subscription_categories"(
  "id" integer primary key autoincrement not null,
  "subscription_id" integer not null,
  "category_id" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("subscription_id") references "subscriptions"("id") on delete cascade,
  foreign key("category_id") references "categories"("id") on delete cascade
);
CREATE UNIQUE INDEX "subscription_categories_subscription_id_category_id_unique" on "subscription_categories"(
  "subscription_id",
  "category_id"
);
CREATE TABLE IF NOT EXISTS "users"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "email" varchar not null,
  "email_verified_at" datetime,
  "password" varchar not null,
  "remember_token" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  "locale" varchar not null default('en'),
  "default_currency_id" integer,
  "enabled_currencies" text,
  "date_format" varchar not null default 'Y-m-d',
  "timezone" varchar not null default 'UTC',
  "smtp_host" varchar,
  "smtp_port" integer,
  "smtp_username" varchar,
  "smtp_password" varchar,
  "smtp_encryption" varchar,
  "smtp_from_address" varchar,
  "smtp_from_name" varchar,
  "use_custom_smtp" tinyint(1) not null default '0',
  foreign key("default_currency_id") references "currencies"("id") on delete set null
);
CREATE UNIQUE INDEX "users_email_unique" on "users"("email");
CREATE TABLE IF NOT EXISTS "currencies"(
  "id" integer primary key autoincrement not null,
  "code" varchar not null,
  "name" varchar not null,
  "symbol" varchar not null,
  "is_active" tinyint(1) not null default('1'),
  "created_at" datetime,
  "updated_at" datetime,
  "user_id" integer,
  "is_system_default" tinyint(1) not null default '0',
  foreign key("user_id") references "users"("id") on delete cascade
);
CREATE UNIQUE INDEX "currencies_code_unique" on "currencies"("code");
CREATE TABLE IF NOT EXISTS "subscriptions"(
  "id" integer primary key autoincrement not null,
  "user_id" integer not null,
  "name" varchar not null,
  "description" text,
  "price" numeric not null,
  "currency_id" integer not null,
  "payment_method_id" integer,
  "billing_interval" integer not null default('1'),
  "start_date" date not null,
  "next_billing_date" date,
  "end_date" date,
  "website_url" varchar,
  "notes" text,
  "created_at" datetime,
  "updated_at" datetime,
  "billing_cycle_day" integer,
  "first_billing_date" date not null,
  "billing_cycle" varchar check("billing_cycle" in('daily', 'weekly', 'monthly', 'quarterly', 'yearly', 'one-time')) not null default 'monthly',
  foreign key("user_id") references users("id") on delete cascade on update no action,
  foreign key("currency_id") references currencies("id") on delete no action on update no action,
  foreign key("payment_method_id") references payment_methods("id") on delete set null on update no action
);
CREATE TABLE IF NOT EXISTS "payment_attachments"(
  "id" integer primary key autoincrement not null,
  "payment_history_id" integer not null,
  "original_name" varchar not null,
  "file_path" varchar not null,
  "file_type" varchar not null,
  "file_size" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("payment_history_id") references "payment_histories"("id") on delete cascade
);
CREATE INDEX "payment_attachments_payment_history_id_index" on "payment_attachments"(
  "payment_history_id"
);
CREATE TABLE IF NOT EXISTS "notification_preferences"(
  "id" integer primary key autoincrement not null,
  "user_id" integer not null,
  "subscription_id" integer,
  "email_enabled" tinyint(1) not null default '1',
  "webhook_enabled" tinyint(1) not null default '0',
  "email_address" varchar,
  "webhook_url" varchar,
  "webhook_headers" text,
  "reminder_intervals" text not null default '[]', "is_default" tinyint(1) not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("user_id") references "users"("id") on delete cascade,
  foreign key("subscription_id") references "subscriptions"("id") on delete cascade
);
CREATE INDEX "notification_preferences_user_id_is_default_index" on "notification_preferences"(
  "user_id",
  "is_default"
);
CREATE INDEX "notification_preferences_user_id_subscription_id_index" on "notification_preferences"(
  "user_id",
  "subscription_id"
);
CREATE UNIQUE INDEX "notification_preferences_user_id_subscription_id_unique" on "notification_preferences"(
  "user_id",
  "subscription_id"
);
CREATE TABLE IF NOT EXISTS "reminder_schedules"(
  "id" integer primary key autoincrement not null,
  "subscription_id" integer not null,
  "user_id" integer not null,
  "days_before" integer not null,
  "due_date" date not null,
  "scheduled_at" datetime not null,
  "sent_at" datetime,
  "status" varchar check("status" in('pending', 'sent', 'failed', 'cancelled')) not null default 'pending',
  "failure_reason" text,
  "retry_count" integer not null default '0',
  "next_retry_at" datetime,
  "channels" text not null default '[]', "created_at" datetime,
  "updated_at" datetime,
  foreign key("subscription_id") references "subscriptions"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete cascade
);
CREATE INDEX "reminder_schedules_scheduled_at_status_index" on "reminder_schedules"(
  "scheduled_at",
  "status"
);
CREATE INDEX "reminder_schedules_subscription_id_due_date_index" on "reminder_schedules"(
  "subscription_id",
  "due_date"
);
CREATE INDEX "reminder_schedules_user_id_status_index" on "reminder_schedules"(
  "user_id",
  "status"
);
CREATE INDEX "reminder_schedules_status_next_retry_at_index" on "reminder_schedules"(
  "status",
  "next_retry_at"
);
CREATE UNIQUE INDEX "reminder_schedules_subscription_id_due_date_days_before_unique" on "reminder_schedules"(
  "subscription_id",
  "due_date",
  "days_before"
);
CREATE TABLE IF NOT EXISTS "notification_logs"(
  "id" integer primary key autoincrement not null,
  "reminder_schedule_id" integer,
  "subscription_id" integer not null,
  "user_id" integer not null,
  "channel" varchar not null,
  "type" varchar not null default 'bill_reminder',
  "recipient" varchar not null,
  "subject" varchar,
  "payload" text,
  "headers" text,
  "status" varchar check("status" in('pending', 'sent', 'delivered', 'failed', 'bounced')) not null default 'pending',
  "sent_at" datetime,
  "delivered_at" datetime,
  "failure_reason" text,
  "provider_response" text,
  "retry_count" integer not null default '0',
  "next_retry_at" datetime,
  "external_id" varchar,
  "tracking_id" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("reminder_schedule_id") references "reminder_schedules"("id") on delete set null,
  foreign key("subscription_id") references "subscriptions"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete cascade
);
CREATE INDEX "notification_logs_user_id_status_created_at_index" on "notification_logs"(
  "user_id",
  "status",
  "created_at"
);
CREATE INDEX "notification_logs_subscription_id_type_created_at_index" on "notification_logs"(
  "subscription_id",
  "type",
  "created_at"
);
CREATE INDEX "notification_logs_channel_status_sent_at_index" on "notification_logs"(
  "channel",
  "status",
  "sent_at"
);
CREATE INDEX "notification_logs_status_next_retry_at_index" on "notification_logs"(
  "status",
  "next_retry_at"
);
CREATE INDEX "notification_logs_tracking_id_index" on "notification_logs"(
  "tracking_id"
);

INSERT INTO migrations VALUES(1,'0001_01_01_000000_create_users_table',1);
INSERT INTO migrations VALUES(2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO migrations VALUES(3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO migrations VALUES(4,'2025_07_29_150123_add_timezone_to_users_table',1);
INSERT INTO migrations VALUES(5,'2025_07_29_150130_create_currencies_table',1);
INSERT INTO migrations VALUES(6,'2025_07_29_150135_create_categories_table',1);
INSERT INTO migrations VALUES(7,'2025_07_29_150140_create_payment_methods_table',1);
INSERT INTO migrations VALUES(8,'2025_07_29_150146_create_subscriptions_table',1);
INSERT INTO migrations VALUES(9,'2025_07_29_150151_create_payment_histories_table',1);
INSERT INTO migrations VALUES(10,'2025_07_29_150156_create_subscription_categories_table',1);
INSERT INTO migrations VALUES(11,'2025_07_30_074149_add_currency_preferences_to_users_table',1);
INSERT INTO migrations VALUES(12,'2025_07_30_081753_add_user_ownership_to_currencies_table',1);
INSERT INTO migrations VALUES(13,'2025_07_30_085114_enhance_payment_methods_table',1);
INSERT INTO migrations VALUES(14,'2025_07_30_094251_update_payment_methods_remove_fields_add_image',1);
INSERT INTO migrations VALUES(15,'2025_07_30_100234_remove_expiration_date_from_payment_methods_table',1);
INSERT INTO migrations VALUES(16,'2025_07_30_152115_add_billing_cycle_day_to_subscriptions_table',1);
INSERT INTO migrations VALUES(17,'2025_07_30_162629_remove_timezone_add_date_format_to_users_table',1);
INSERT INTO migrations VALUES(18,'2025_07_30_171810_add_first_billing_date_to_subscriptions_table',1);
INSERT INTO migrations VALUES(19,'2025_07_30_173009_make_next_billing_date_nullable_in_subscriptions_table',1);
INSERT INTO migrations VALUES(20,'2025_07_31_124730_create_payment_attachments_table',1);
INSERT INTO migrations VALUES(21,'2025_07_31_200000_simplify_subscription_status_add_onetime_billing',1);
INSERT INTO migrations VALUES(22,'2025_08_03_163345_create_notification_preferences_table',1);
INSERT INTO migrations VALUES(23,'2025_08_03_163353_create_reminder_schedules_table',1);
INSERT INTO migrations VALUES(24,'2025_08_03_163359_create_notification_logs_table',1);
INSERT INTO migrations VALUES(25,'2025_08_03_163405_add_timezone_to_users_table',1);
INSERT INTO migrations VALUES(27,'2025_08_04_033216_remove_timezone_from_notification_preferences_table',2);
INSERT INTO migrations VALUES(28,'2025_08_04_041217_add_use_custom_smtp_to_users_table',2);
