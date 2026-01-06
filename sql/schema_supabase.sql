-- Fitshop Hub / Health&Fitness - Supabase Postgres schema
-- Safe to run multiple times (uses IF NOT EXISTS / guarded DO blocks where needed)

-- USERS
create table if not exists public.users (
  id bigserial primary key,
  name varchar(120) not null,
  email varchar(190) not null unique,
  password_hash varchar(255) not null,
  photo_url text null,

  goal text not null default 'general_health',
  activity_level text not null default 'light',
  equipment text not null default 'none',
  diet text not null default 'none',

  plan_json jsonb null,

  steps_goal integer not null default 10000,

  created_at timestamptz not null default now()
);

-- Optional: keep values similar to your old MySQL ENUMs (not required but recommended)
do $$
begin
  if not exists (
    select 1 from pg_constraint where conname = 'users_goal_chk'
  ) then
    alter table public.users
      add constraint users_goal_chk
      check (goal in ('lose_weight','build_muscle','endurance','general_health'));
  end if;

  if not exists (
    select 1 from pg_constraint where conname = 'users_activity_level_chk'
  ) then
    alter table public.users
      add constraint users_activity_level_chk
      check (activity_level in ('sedentary','light','moderate','active'));
  end if;

  if not exists (
    select 1 from pg_constraint where conname = 'users_equipment_chk'
  ) then
    alter table public.users
      add constraint users_equipment_chk
      check (equipment in ('none','home_minimal','gym_access'));
  end if;

  if not exists (
    select 1 from pg_constraint where conname = 'users_diet_chk'
  ) then
    alter table public.users
      add constraint users_diet_chk
      check (diet in ('none','vegetarian','keto'));
  end if;
end $$;


-- ORDERS
create table if not exists public.orders (
  id bigserial primary key,
  user_id bigint null references public.users(id) on delete set null,
  name varchar(160) not null,
  address text not null,
  payment text not null,
  total numeric(10,2) not null,
  created_at timestamptz not null default now()
);

do $$
begin
  if not exists (
    select 1 from pg_constraint where conname = 'orders_payment_chk'
  ) then
    alter table public.orders
      add constraint orders_payment_chk
      check (payment in ('gcash','maya'));
  end if;
end $$;

create index if not exists idx_orders_user_id on public.orders(user_id);


-- ORDER ITEMS
create table if not exists public.order_items (
  id bigserial primary key,
  order_id bigint not null references public.orders(id) on delete cascade,
  product_id integer not null,
  title varchar(200) not null,
  qty integer not null,
  price numeric(10,2) not null
);

create index if not exists idx_order_items_order_id on public.order_items(order_id);


-- SHIPMENTS (one per order)
create table if not exists public.shipments (
  id bigserial primary key,
  order_id bigint not null references public.orders(id) on delete cascade,
  carrier varchar(60) not null default 'LocalCourier',
  tracking_no varchar(80) unique,
  current_status text not null default 'Order Placed',
  history jsonb not null default '[]'::jsonb,
  updated_at timestamptz not null default now()
);

do $$
begin
  if not exists (
    select 1 from pg_constraint where conname = 'shipments_status_chk'
  ) then
    alter table public.shipments
      add constraint shipments_status_chk
      check (current_status in ('Order Placed','Packed','Shipped','Out for Delivery','Delivered'));
  end if;
end $$;

create unique index if not exists uniq_shipments_order_id on public.shipments(order_id);


-- FOOD LOGS
create table if not exists public.food_logs (
  id bigserial primary key,
  user_id bigint not null references public.users(id) on delete cascade,
  title varchar(160) not null,
  photo_path text null,

  calories integer not null default 0,
  protein_g numeric(6,2) not null default 0,
  carbs_g numeric(6,2) not null default 0,
  fat_g numeric(6,2) not null default 0,

  created_at timestamptz not null default now()
);

create index if not exists idx_food_logs_user_id_created_at on public.food_logs(user_id, created_at desc);


-- STEPS LOGS (daily aggregation)
create table if not exists public.steps_logs (
  id bigserial primary key,
  user_id bigint not null references public.users(id) on delete cascade,
  step_date date not null,
  steps integer not null default 0
);

-- Required for ON CONFLICT (user_id, step_date) in your PHP
create unique index if not exists uniq_steps_user_date on public.steps_logs(user_id, step_date);
create index if not exists idx_steps_logs_user_date on public.steps_logs(user_id, step_date desc);


-- WORKOUT SESSIONS (gym)
create table if not exists public.workout_sessions (
  id bigserial primary key,
  user_id bigint not null references public.users(id) on delete cascade,
  program_id integer not null,
  program_title varchar(200) not null,
  started_at timestamptz not null,
  ended_at timestamptz null,
  total_duration_sec integer not null default 0,
  total_volume numeric(12,2) not null default 0,
  notes text null
);

create index if not exists idx_workout_sessions_user_ended_at on public.workout_sessions(user_id, ended_at desc);


-- WORKOUT SETS (one row per performed set)
create table if not exists public.workout_sets (
  id bigserial primary key,
  session_id bigint not null references public.workout_sessions(id) on delete cascade,
  exercise_order integer not null,
  exercise_name varchar(160) not null,
  set_number integer not null,
  target_reps integer null,
  performed_reps integer null,
  weight_kg numeric(6,2) null,
  rpe numeric(3,1) null,
  rest_sec integer null
);

create index if not exists idx_workout_sets_session_id on public.workout_sets(session_id);


-- ACTIVITY SESSIONS (choreo + guides)
create table if not exists public.activity_sessions (
  id bigserial primary key,
  user_id bigint not null references public.users(id) on delete cascade,
  activity_type text not null,
  item_id integer not null,
  title varchar(200) not null,
  started_at timestamptz not null,
  ended_at timestamptz null,
  duration_sec integer not null default 0,
  completed_steps integer not null default 0,
  notes text null
);

do $$
begin
  if not exists (
    select 1 from pg_constraint where conname = 'activity_sessions_type_chk'
  ) then
    alter table public.activity_sessions
      add constraint activity_sessions_type_chk
      check (activity_type in ('choreo','guide'));
  end if;
end $$;

create index if not exists idx_activity_sessions_user_ended_at on public.activity_sessions(user_id, ended_at desc);


-- API TOKENS
create table if not exists public.api_tokens (
  id bigserial primary key,
  user_id bigint not null references public.users(id) on delete cascade,
  token_hash text not null unique,
  name text null,
  created_at timestamptz not null default now(),
  last_used_at timestamptz null,
  revoked_at timestamptz null
);

create index if not exists api_tokens_user_id_idx on public.api_tokens(user_id);
