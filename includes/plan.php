<?php
function fh_build_plan(array $profile): array {
  $goal = $profile['goal'] ?? 'general_health';
  $activity = $profile['activity_level'] ?? 'light';
  $equipment = $profile['equipment'] ?? 'none';
  $diet = $profile['diet'] ?? 'none';
  $minutesBase = [ 'sedentary'=>90, 'light'=>120, 'moderate'=>150, 'active'=>180 ];
  $minutes = $minutesBase[$activity] ?? 120;
  $schedule = [];
  switch ($goal) {
    case 'lose_weight':
      $split = [ 'Mon'=>'Cardio 30m', 'Tue'=>'Full-body 30m', 'Wed'=>'Cardio 30m', 'Thu'=>'Core+HIIT 30m', 'Fri'=>'Cardio 30m', 'Sat'=>'Active recovery 20m', 'Sun'=>'Rest' ];
      $modules = ['Guides: Fat loss basics','Choreography: Low-impact','Equipment: Mat, bands'];
      break;
    case 'build_muscle':
      $split = [ 'Mon'=>'Upper 45m', 'Tue'=>'Lower 45m', 'Wed'=>'Cardio 20m', 'Thu'=>'Push 45m', 'Fri'=>'Pull 45m', 'Sat'=>'Core+Mobility 25m', 'Sun'=>'Rest' ];
      $modules = ['Guides: Hypertrophy','Equipment: Dumbbells','Supplements: Whey protein'];
      $minutes += 30;
      break;
    case 'endurance':
      $split = [ 'Mon'=>'Run 30m', 'Tue'=>'Mobility 20m', 'Wed'=>'Run 40m', 'Thu'=>'Strength 30m', 'Fri'=>'Run 30m', 'Sat'=>'Long cardio 60m', 'Sun'=>'Rest' ];
      $modules = ['Choreography: Cardio routines','Guides: Running form'];
      break;
    default:
      $split = [ 'Mon'=>'Full-body 30m', 'Tue'=>'Walk 20m', 'Wed'=>'Core 20m', 'Thu'=>'Mobility 20m', 'Fri'=>'Cardio 25m', 'Sat'=>'Fun activity 30m', 'Sun'=>'Rest' ];
      $modules = ['Guides: Foundations','Choreography: Beginner'];
  }
  // Equipment adaptation
  if ($equipment === 'gym_access') {
    $modules[] = 'Equipment: Adjustable dumbbells, bench';
  } elseif ($equipment === 'home_minimal') {
    $modules[] = 'Equipment: Resistance bands, mat';
  }
  // Diet suggestions
  $dietTips = [];
  if ($goal==='lose_weight') { $dietTips[] = 'Calorie deficit 300–500 kcal'; }
  if ($goal==='build_muscle') { $dietTips[] = 'Protein 1.6–2.2 g/kg'; }
  if ($diet==='vegetarian') { $dietTips[] = 'Consider plant protein + B12'; }
  if ($diet==='keto') { $dietTips[] = 'Electrolyte hydration'; }

  return [
    'goal'=>$goal,
    'activity_level'=>$activity,
    'recommended_minutes_per_week'=>$minutes,
    'weekly_schedule'=>$split,
    'modules'=>$modules,
    'diet_tips'=>$dietTips,
    'suggested_shop_categories'=>
      ($goal==='build_muscle'?['equipment','supplements','snacks']:['equipment','snacks'])
  ];
}
