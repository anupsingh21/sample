<?php

namespace Drupal\lions_general\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Url;
use Drupal\lions_general\LionsGeneralExport;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;

/**
 * Lions General paragraph Report.
 */
class LionsGeneralParagraphReport extends FormBase {

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
   * Active database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * LionsGeneralParagraphReport constructor.
   *
   * @param \Drupal\lions_general\LionsGeneralExport $lions_general
   *   The lions general service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   A request stack symfony instance.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection to be used.
   */
  public function __construct(LionsGeneralExport $lions_general, RequestStack $request_stack, Connection $database) {
    $this->lionsGeneral = $lions_general;
    $this->requestStack = $request_stack;
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('lions_general.export'),
      $container->get('request_stack'),
      $container->get('database'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'lions_general_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $parameter = $this->requestStack->getCurrentRequest()->query->all();

    $form['node_id'] = [
      '#type' => 'textfield',
      '#title' => t('Page Id'),
      '#default_value' => $parameter['node_id'] ?? '',
    ];

    // Query to get the Paragraph labels.
    $query = $this->database->select('paragraphs_item_field_data', 'pd');
    $query->fields('pd', ['type']);
    $options = $query->distinct()->execute()->fetchAllKeyed(0, 0);
    foreach ($options as $option) {
      $this->lionsGeneral->getParagraphLabel($option);
    }
    $options = $this->lionsGeneral->getParagraphLabel();

    $form['type'] = [
      '#type' => 'select',
      '#title' => ('Main Component Name'),
      '#multiple' => TRUE,
      '#options' => $options,
      '#default_value' => $parameter['type'] ?? '',
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#name' => 'submit',
      '#value' => $this->t('Apply'),
      '#button_type' => 'primary',
    ];
    $form['actions']['reset'] = [
      '#type' => 'submit',
      '#name' => 'reset',
      '#value' => $this->t('Reset'),
      '#button_type' => 'secondary',
    ];

    $header = $this->lionsGeneral->getHeader();

    $result = $this->lionsGeneral->getParagraphQuery($parameter);

    $options = [];
    foreach ($result as $row) {
      $options[] = $this->lionsGeneral->setBody($row);
    }

    $form['table'] = [
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => $options,
      '#empty' => t('No data found'),
      '#weight' => 100,
    ];

    $form['pager'] = [
      '#type' => 'pager',
      '#weight' => 100,
    ];

    unset($parameter['page']);
    if (!empty($parameter)) {
      $url_object = Url::fromRoute('lions_general.csv_export', $parameter);
      $form['link'] = [
        '#type' => 'link',
        '#url' => $url_object,
        '#title' => $this->t('CSV'),
        '#weight' => 100,
        '#attributes' => [
          'class' => 'feed-icon',
        ],
        '#prefix' => "<div class='feed-icons'><div class='csv-feed views-data-export-feed'>",
        '#suffix' => "</div></div>",
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $button_name = $form_state->getTriggeringElement()['#name'];
    if ($button_name === 'submit') {
      $parameter = [];
      $node_id = $form_state->getValue('node_id');
      $type = $form_state->getValue('type');
      if (!empty($node_id)) {
        $parameter['node_id'] = $node_id;
      }
      if (!empty($type)) {
        $parameter['type'] = $type;
      }
      $path = Url::fromRoute('lions_general.report', $parameter)->toString();
    }
    if ($button_name === 'reset') {
      $path = Url::fromRoute('lions_general.report')->toString();
    }
    $response = new RedirectResponse($path);
    $response->send();
  }

}
