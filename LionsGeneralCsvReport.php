<?php

namespace Drupal\lions_general\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Drupal\lions_general\LionsGeneralExport;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class LionsGeneralCsvReport export csv data.
 *
 * @package Drupal\my_module\Controller
 */
class LionsGeneralCsvReport extends ControllerBase {

  /**
   * The form builder.
   *
   * @var \Drupal\lions_general\LionsGeneralExport
   */
  protected $lionsGeneral;

  /**
   * A request stack symfony instance.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * LionsGeneralCsvReport constructor.
   *
   * @param \Drupal\lions_general\LionsGeneralExport $lions_general
   *   The form builder.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   A request stack symfony instance.
   */
  public function __construct(LionsGeneralExport $lions_general, RequestStack $request_stack) {
    $this->lionsGeneral = $lions_general;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The Drupal service container.
   *
   * @return static
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('lions_general.export'),
      $container->get('request_stack'),
    );
  }

  /**
   * Export a CSV of data.
   */
  public function build() {
    $handle = fopen('php://temp', 'w+');

    $header = $this->lionsGeneral->getHeader();
    fputcsv($handle, $header);

    $parameter = $this->requestStack->getCurrentRequest()->query->all();
    $entities = $this->lionsGeneral->getParagraphQuery($parameter, TRUE);
    foreach ($entities as $row) {
      $data = $this->buildRow($row);
      fputcsv($handle, array_values($data));
    }
    rewind($handle);

    $csv_data = stream_get_contents($handle);

    fclose($handle);

    $response = new Response();

    $response->headers->set('Content-Type', 'text/csv');
    $response->headers->set('Content-Disposition', 'attachment; filename="report.csv"');

    $response->setContent($csv_data);

    return $response;
  }

  /**
   * Fetches data and builds CSV row.
   *
   * @return array
   *   Row data.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function buildRow($row) {
    return $this->lionsGeneral->setBody($row, TRUE);
  }

}
