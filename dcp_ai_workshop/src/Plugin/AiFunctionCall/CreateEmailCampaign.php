<?php

namespace Drupal\dcp_ai_workshop\Plugin\AiFunctionCall;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;
use Drupal\ai\Utility\ContextDefinitionNormalizer;
use Drupal\ai_agents\PluginInterfaces\AiAgentContextInterface;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation for creating email campaign.
 */
#[FunctionCall(
  id: 'ai_agent:create_email_campaign',
  function_name: 'ai_agents_create_email_campaign',
  name: 'Create Email Campaign',
  description: 'This tool can be used to send emails to all subscribed users with recipe recommendations',
  group: 'modification_tools',
  context_definitions: [
    'subject' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Email subject"),
      description: new TranslatableMarkup("A catchy subject for the mail."),
      required: TRUE,
    ),
    'mail_body' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Email_body"),
      description: new TranslatableMarkup("The HTML markup that corresponds to the mail body of the campaign with recipe recommendations."),
      required: FALSE,
    ),
  ],
)]
class CreateEmailCampaign extends FunctionCallBase implements ExecutableFunctionCallInterface, AiAgentContextInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Load from dependency injection container.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): FunctionCallInterface|static {
    $instance = new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      new ContextDefinitionNormalizer(),
    );
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * The result message.
   *
   * @var string
   */
  protected string $resultMessage = "";

  /**
   * {@inheritdoc}
   */
  public function execute() {
    try {
      // Collect the context values.
      $subject = $this->getContextValue('subject');
      $body = $this->getContextValue('mail_body');

      // Create the recipe node.
      $node = Node::create([
        'type' => 'email_campaign',
        'title' => $subject,
        'langcode' => 'en',
        'uid' => \Drupal::currentUser()->id(),
      ]);

      // Set cooking directions.
      if (!empty($body)) {
        $node->set('field_email_body', [
          'value' => $body,
          'format' => 'full_html',
        ]);
      }

      // Save the node.
      $node->save();

      $this->setOutput(sprintf(
        'Mail with subject "%s" sent successfully',
        $subject,
      ));

    } catch (\Exception $e) {
      $this->setOutput('Error: Failed to send the mail. ' . $e->getMessage());
      \Drupal::logger('ai_agents')->error('Recipe creation failed: @message', ['@message' => $e->getMessage()]);
    }
  }

}
