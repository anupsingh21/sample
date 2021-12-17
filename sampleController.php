<?php

namespace Drupal\sample\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Drupal\sample\sampleService;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class sampleController export csv data.
 *
 * @package Drupal\my_module\Controller
 */
class sampleController extends ControllerBase {

  /**
   * The form builder.
   *
   * @var \Drupal\sample\sampleService
   */
  protected $sampleService;

  /**
   * A request stack symfony instance.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * sampleController constructor.
   *
   * @param \Drupal\sample\sampleService $sample
   *   The form builder.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   A request stack symfony instance.
   */
  public function __construct(sampleService $sample, RequestStack $request_stack) {
    $this->sampleService = $sample;
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
      $container->get('sample.export'),
      $container->get('request_stack'),
    );
  }

  /**
   * Export a CSV of data.
   */
  public function build() {
    $handle = fopen('php://temp', 'w+');

    $header = $this->sampleService->getHeader();
    fputcsv($handle, $header);

    $parameter = $this->requestStack->getCurrentRequest()->query->all();
    $entities = $this->sampleService->getParagraphQuery($parameter, TRUE);
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
    return $this->sampleService->setBody($row, TRUE);
  }

}
