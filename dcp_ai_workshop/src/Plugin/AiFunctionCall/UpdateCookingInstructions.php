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
  id: 'ai_agent:update_cooking_instructions',
  function_name: 'ai_agents_update_cooking_instructions',
  name: 'Update cooking instructions.',
  description: 'This tool can be used to update cooking instructions of an existing recipe node. The cooking instructions would be placed in a full_html text field. So provide the value in proper HTML format.',
  group: 'modification_tools',
  context_definitions: [
    'node_id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Node ID"),
      description: new TranslatableMarkup("The node ID of the recipe node."),
      required: TRUE,
    ),
    'cooking_instructions' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Cooking instructions"),
      description: new TranslatableMarkup("The raw HTML of cooking instructions."),
      required: FALSE,
    ),
  ],
)]
class UpdateCookingInstructions extends FunctionCallBase implements ExecutableFunctionCallInterface, AiAgentContextInterface {

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
      $node_id = $this->getContextValue('node_id');
      $cooking_instructions = $this->getContextValue('cooking_instructions');

      // Create the recipe node.
      $node = Node::load($node_id);
      if (!$node) {
        $this->setOutput('Error: No node found with ID %s', $node_id);
        return;
      }


      $this->setOutput(\json_encode(
        ['cooking_instructions' => $cooking_instructions]
      ));

    }
    catch (\Exception $e) {
      $$this->setOutput('Error: Failed to update recipe node. ' . $e->getMessage());
      \Drupal::logger('ai_agents')->error('Recipe creation failed: @message', ['@message' => $e->getMessage()]);
    }
  }

}
