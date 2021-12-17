<?php

namespace Drupal\lions_general;

use Drupal\Core\Database\Connection;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * LionsGeneralExport service to get the common logic for csv and report.
 */
class LionsGeneralExport {

  use StringTranslationTrait;

  /**
   * Db connection variable.
   *
   * @var Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Paragraph label variable to store the values.
   *
   * @var array
   *  array for labels for paragraph.
   */
  protected $paragraphLabel = [];

  /**
   * A request stack symfony instance.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * LionsGeneralExport Constructor to inject dependecy.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   Connection for database.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   Translation langudage.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   Request stack variable.
   */
  public function __construct(Connection $connection, TranslationInterface $string_translation, RequestStack $request_stack) {
    $this->connection = $connection;
    $this->stringTranslation = $string_translation;
    $this->requestStack = $request_stack;
  }

  /**
   * Funtion to return the result for paragraph report.
   *
   * @param array $parameter
   *   Parameter for the query from url.
   * @param bool $csv
   *   Flag to csv check.
   *
   * @return array
   *   array of query result.
   */
  public function getParagraphQuery(array $parameter, $csv = FALSE) {
    $query = $this->generateQuery();
    $target_ids = $this->getTargetIds();
    $result = [];
    if (!empty($target_ids)) {
      $query->condition('pd.id', $target_ids, 'IN');
    }
    if ($csv) {
      $result = $query->execute()->fetchAll();
    }
    else {
      $pager = $query->extend('Drupal\Core\Database\Query\PagerSelectExtender')->limit(100);
      $result = $pager->execute()->fetchAll();
    }

    return $result;
  }

  /**
   * Helper funtion to set the header.
   *
   * @return array
   *   array of query result.
   */
  public function getHeader() {
    return [
      'nid' => $this->t('Page Id'),
      'title' => $this->t('Page Title'),
      'url' => $this->t('Page URL'),
      'type' => $this->t('Main Component Name'),
    ];
  }

  /**
   * Helper funtion to set the body.
   *
   * @param object $row
   *   Row value for csv.
   * @param bool $csv
   *   Flag for csv.
   *
   * @return array
   *   array of query result.
   */
  public function setBody($row, $csv = FALSE) {
    $url = Url::fromRoute('entity.node.canonical', ['node' => $row->nid], ['absolute' => TRUE])->toString();
    $title = ($csv) ? $row->title : Link::createFromRoute($row->title, 'entity.node.canonical', ['node' => $row->nid]);
    return [
      'nid' => $row->nid,
      'title' => $title,
      'url' => $url,
      'type' => $this->getParagraphLabel($row->type),
    ];
  }

  /**
   * Helper funtion to get the label for paragraph.
   *
   * @param string $paragraph_key
   *   Empty value for paragraph.
   *
   * @return array
   *   array of query result.
   */
  public function getParagraphLabel($paragraph_key = '') {
    if (empty($paragraph_key)) {
      return $this->paragraphLabel;
    }
    elseif (isset($this->paragraphLabel[$paragraph_key])) {
      return $this->paragraphLabel[$paragraph_key];
    }
    else {
      $paragraph_label = \Drupal::entityTypeManager()
        ->getStorage('paragraphs_type')
        ->load($paragraph_key)
        ->label();
      $this->paragraphLabel[$paragraph_key] = $paragraph_label;
      return $this->paragraphLabel[$paragraph_key];
    }
  }

  /**
   * Helper funtion to extract the used paragraph to limit the result.
   *
   * @return array
   *   array of query result.
   */
  public function getTargetIds() {
    $query = $this->generateQuery();
    $result = $query->execute()->fetchAll();

    $nid = $target_ids = [];
    foreach ($result as $key => $val) {
      $nid[$val->nid][$val->parent_field_name] = $val->parent_field_name;
    }
    // Extract paragraph ids.
    foreach ($nid as $key => $val) {
      $node = Node::load($key);
      foreach ($val as $k => $v) {
        $field_data = $node->get($k)->getValue();
        // Filter duplicate paragraph type.
        $unique_para = [];
        foreach ($field_data as $delta => $target_id) {
          $paragraph = Paragraph::load($target_id['target_id']);
          $bundle = $paragraph->bundle();
          if (isset($unique_para[$bundle])) {
            continue;
          }
          else {
            $unique_para[$bundle] = $target_id['target_id'];
            $target_ids[$target_id['target_id']] = $target_id['target_id'];
          }
        }
      }
    }
    return $target_ids;
  }

  /**
   * Helper funtion to get the base query.
   *
   * @return array
   *   array of query result.
   */
  public function generateQuery() {
    $parameter = $this->requestStack->getCurrentRequest()->query->all();
    $query = $this->connection->select('paragraphs_item_field_data', 'pd');
    $query->join('node_field_data', 'nfd', 'nfd.nid = pd.parent_id');
    $query->fields('pd', ['type', 'parent_field_name']);
    $query->fields('nfd', ['nid', 'title']);
    $query->condition('pd.langcode', 'en');
    $query->condition('nfd.langcode', 'en');
    $query->condition('pd.status', 1);
    $query->condition('nfd.status', 1);
    $query->condition('pd.parent_type', 'node');
    $query->orderBy('nfd.nid', 'ASC');
    if (isset($parameter['node_id'])) {
      $query->condition('pd.parent_id', $parameter['node_id']);
    }
    if (isset($parameter['type'])) {
      $query->condition('pd.type', $parameter['type'], 'IN');
    }
    return $query;
  }

}
