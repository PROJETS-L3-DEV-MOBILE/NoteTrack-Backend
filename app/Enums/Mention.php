<?php

namespace App\Enums;

enum Mention: string
{
  case FAILED = 'failed';
  case PASS = 'pass';
  case SATISFACTORY = 'satisfactory';
  case GOOD = 'good';
  case EXCELLENT = 'excellent';
}
