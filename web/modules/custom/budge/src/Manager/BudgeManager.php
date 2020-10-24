<?php


namespace Drupal\budge\Manager;

use Drupal\budge\Gateway\BudgeGateway;

/**
 * Class BudgeManager
 *
 * @package Drupal\budge
 */
class BudgeManager {

  const FIELDS_BUDGET = [
    'field_start_amount',
    'field_credits',
    'field_monthly_expenses',
    'field_ponctual_expenses',
  ];

  const FIELDS_PARAGRAPHS = [
    'field_date',
    'field_amount',
    'field_title',
  ];

  /**
   * @var \Drupal\budge\Gateway\BudgeGateway
   */
  protected $budgeGateway;

  /**
   * BudgeExportManager constructor.
   *
   * @param \Drupal\budge\Gateway\BudgeGateway $budgeGateway
   */
  public function __construct(BudgeGateway $budgeGateway) {
    $this->budgeGateway = $budgeGateway;
  }

  /**
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getBudget() {
    $budgetEntity = $this->getBudgetEntity();
    if ($budgetEntity) {
      $list = [];
      foreach (self::FIELDS_BUDGET as $field) {
        if ($budgetEntity->hasField($field)) {
          if ($field !== 'field_start_amount') {
            $list[$field] = $this->manageParagraphFields($field,
              $budgetEntity->get($field)->getValue());
          }
          else {
            $list[$field] = $budgetEntity->get($field)->getValue()[0]['value'];
          }
        }
      }
    }
    return $this->sortList($list);
  }

  /**
   * @return \Drupal\Core\Entity\EntityInterface|null
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getBudgetEntity() {
    return $this->budgeGateway->fetchBudgeEntity();
  }

  /**
   * @param $pids
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function manageParagraphFields($type, $pids) {
    $paragraphEntities = $this->budgeGateway->fetchParagraphEntities($pids);
    $output = [];
    if (!empty($paragraphEntities)) {
      foreach ($paragraphEntities as $paragraphEntity) {
        $list = [];
        foreach (self::FIELDS_PARAGRAPHS as $field) {
          if ($paragraphEntity->hasField($field)) {
            $list[$field] = $paragraphEntity->get($field)
              ->getValue()[0]['value'];
            $list['type'] = $type;
          }
        }
        $output[] = $list;
      }
    }
    return $output;
  }

  /**
   * @param array $list
   *
   * @return array[]
   */
  protected function sortList(array $list) {
    $total = array_merge($list['field_monthly_expenses'],
      $list['field_ponctual_expenses'],
      $list['field_credits']);
    $sorted = [];
    $amount = $list['field_start_amount'];
    $totalPonctualExpenses = 0;
    $totalMonthlyExpenses = 0;
    $expenses = ['monthly' => 0, 'ponctual' => 0];

    foreach ($total as $key => $item) {
      $type = $item['type'];
      if ($type == "field_credits") {
        $amount += $item['field_amount'];
        $sorted[] = [
          'Titre' => $item['field_title'],
          'Type' => 'Ajout',
          'Date' => $item['field_date'],
          'Montant' => '+' . number_format($item['field_amount'], 2,',', ''),
          'Solde' => number_format($amount, 2, ',', ''),
        ];
      }
      elseif ($type == "field_monthly_expenses") {
        $amount -= $item['field_amount'];
        $totalMonthlyExpenses += $item['field_amount'];
        $expenses['monthly'] = number_format($totalMonthlyExpenses, 2, ',', '');
        $sorted[] = [
          'Titre' => $item['field_title'],
          'Type' => 'Dépense mensuelle',
          'Date' => $item['field_date'],
          'Montant' => '-' . number_format($item['field_amount'],2, ',', ''),
          'Solde' => number_format($amount, 2, ',', ''),
        ];
      }
      elseif ($type == "field_ponctual_expenses") {
        $amount -= $item['field_amount'];
        $totalPonctualExpenses += $item['field_amount'];
        $expenses['ponctual'] = number_format($totalPonctualExpenses, 2,',', '');
        $sorted[] = [
          'Titre' => $item['field_title'],
          'Type' => 'Dépense ponctuelle',
          'Date' => $item['field_date'],
          'Montant' => '-' . number_format($item['field_amount'], 2, ',', ''),
          'Solde' => number_format($amount, 2, ',', ''),
        ];
      }

    }
    return [
      'list' => $list,
      'sorted' => $sorted,
      'currentAmount' => $amount,
      'expenses' => $expenses,
    ];
  }

}