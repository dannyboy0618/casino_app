<?php namespace App\Games;

use App\Models\Game;
use App\Games\Kernel\GameCategory;
use App\Games\Kernel\Module\General\HouseEdgeModule;
use App\Games\Kernel\Module\General\Wrapper\MultiplierCanBeLimited;
use App\Games\Kernel\ProvablyFairResult;
use App\Games\Kernel\Quick\QuickGame;
use App\Games\Kernel\Metadata;
use App\Games\Kernel\ProvablyFair;
use App\Games\Kernel\Data;
use App\Games\Kernel\Quick\SuccessfulQuickGameResult;
use App\Games\Kernel\RejectedGameResult;

class Dice extends QuickGame implements MultiplierCanBeLimited {

  function metadata(): Metadata {
    return new class extends Metadata {
      public function id(): string {
        return "dice";
      }

      public function name(): string {
        return "Dice";
      }

      public function icon(): string {
        return "fad fa-dice-d20";
      }

      public function category(): array {
        return [GameCategory::$originals, GameCategory::$instant];
      }

      public function image(): string {
        return "/img/game/dice.png";
      }
    };
  }

  public function start($user, Data $data) {
    if ($data->game()->target !== 'lower' && $data->game()->target !== 'higher') return new RejectedGameResult(1, 'Invalid dice target');
    if ($data->game()->target == 'lower' && $data->game()->value > 96) return new RejectedGameResult(2, 'Invalid value');
    if ($data->game()->target == 'higher' && $data->game()->value < 5) return new RejectedGameResult(2, 'Invalid value');
    if ($data->game()->value < 5 || $data->game()->value > 96) return new RejectedGameResult(2, 'Invalid value');

    return new SuccessfulQuickGameResult((new ProvablyFair($this))->result(), function (SuccessfulQuickGameResult $result, array $transformedResults) use ($user, $data) {
      $low = $data->game()->target == 'lower' ? 0 : $data->game()->value;
      $high = $data->game()->target === 'higher' ? 100 : $data->game()->value;
      $number = $transformedResults[0];

      if ($this->isLoss($result->provablyFairResult(), $data)) $result->lose();
      //if($this->isLoss($number, $data)) $result->lose();
      else $result->win($user, $data, HouseEdgeModule::apply($this, $this->payout($low, $high)));

      return [
        'target' => $data->game()->target,
        'low' => $low,
        'high' => $high,
        'value' => $number
      ];
    });
  }

  public function isLoss(ProvablyFairResult $result, Data $data): bool {
    $number = $result->result()[0];
    return !(($data->game()->target == "lower" && $number <= $data->game()->value) || ($data->game()->target == "higher" && $number >= $data->game()->value));
  }

    // public function isLoss($result, Data $data): bool {
    //     //$number = $result->result()[0];
    //     return !(($data->game()->target == "lower" && $result <= $data->game()->value) || ($data->game()->target == "higher" && $result >= $data->game()->value));
    // }

  function result(ProvablyFairResult $result): array {
    return [(floor($result->extractFloat() * 101))];
  }

  private function payout($min, $max) {
    if ($min == $max) return 100.00;
    $range = $max - $min;
    return 100.00 / $range;
  }

  public function multiplier(?Game $game, ?Data $data, ProvablyFairResult $result): float {
    $low = $data->game()->target == 'lower' ? 0 : $data->game()->value;
    $high = $data->game()->target === 'higher' ? 100 : $data->game()->value;

    return HouseEdgeModule::apply($this, $this->payout($low, $high));
  }

  public function data(): array {
    return [
      'dice-house-edge' => HouseEdgeModule::get($this, 0)
    ];
  }

  public function getBotData(): array {
    return [
      'target' => mt_rand(0, 100) <= 50 ? 'lower' : 'higher',
      'value' => mt_rand(5, 96)
    ];
  }

}
