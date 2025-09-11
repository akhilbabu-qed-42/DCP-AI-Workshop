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
 * Plugin implementation for creating recipe nodes.
 */
#[FunctionCall(
  id: 'ai_agent:create_recipe_node',
  function_name: 'ai_agents_create_recipe_node',
  name: 'Create Recipe Node',
  description: 'This tool can be used to add a new Recipe content to the website.',
  group: 'modification_tools',
  context_definitions: [
    'title' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Recipe Title"),
      description: new TranslatableMarkup("The title of the recipe."),
      required: TRUE,
    ),
    'preparation_time' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup("Preparation Time"),
      description: new TranslatableMarkup("Preparation time in minutes."),
      required: FALSE,
    ),
    'cooking_time' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup("Cooking Time"),
      description: new TranslatableMarkup("Cooking time in minutes."),
      required: FALSE,
    ),
    'servings' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup("Number of Servings"),
      description: new TranslatableMarkup("Number of servings this recipe makes."),
      required: FALSE,
    ),
    'difficulty' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Difficulty Level"),
      description: new TranslatableMarkup("Difficulty level: easy, medium, or hard."),
      required: FALSE,
    ),
    'recipe_category' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup("Recipe Categories"),
      description: new TranslatableMarkup("Array of taxonomy term IDs for recipe categories."),
      required: FALSE,
    ),
    'tags' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup("Recipe Tags"),
      description: new TranslatableMarkup("Array of taxonomy term IDs for recipe tags."),
      required: FALSE,
    ),
    'summary' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Recipe Summary"),
      description: new TranslatableMarkup("Brief description or summary of the recipe."),
      required: FALSE,
    ),
    'ingredients' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup("Ingredients"),
      description: new TranslatableMarkup("Array of ingredient objects with 'quantity' and 'item' properties."),
      required: FALSE,
    ),
    'directions' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Cooking Directions"),
      description: new TranslatableMarkup("Step-by-step cooking instructions as HTML text."),
      required: FALSE,
    ),
  ],
)]
class CreateRecipeNode extends FunctionCallBase implements ExecutableFunctionCallInterface, AiAgentContextInterface {

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
      $title = $this->getContextValue('title');
      $preparation_time = $this->getContextValue('preparation_time');
      $cooking_time = $this->getContextValue('cooking_time');
      $servings = $this->getContextValue('servings');
      $difficulty = $this->getContextValue('difficulty');
      $recipe_category = $this->getContextValue('recipe_category');
      $tags = $this->getContextValue('tags');
      $summary = $this->getContextValue('summary');
      $ingredients = $this->getContextValue('ingredients');
      $directions = $this->getContextValue('directions');

      // Validate required fields.
      if (empty($title)) {
        $this->resultMessage = 'Error: Recipe title is required.';
        return;
      }

      // Validate difficulty if provided.
      if (!empty($difficulty) && !in_array($difficulty, ['easy', 'medium', 'hard'])) {
        $this->resultMessage = 'Error: Difficulty must be one of: easy, medium, hard.';
        return;
      }

      // Create the recipe node.
      $node = Node::create([
        'type' => 'recipe',
        'title' => $title,
        'langcode' => 'en',
        'uid' => \Drupal::currentUser()->id(),
      ]);

      // Set preparation time.
      if (!empty($preparation_time) && is_numeric($preparation_time)) {
        $node->set('field_preparation_time', $preparation_time);
      }

      // Set cooking time.
      if (!empty($cooking_time) && is_numeric($cooking_time)) {
        $node->set('field_cooking_time', $cooking_time);
      }

      // Set number of servings.
      if (!empty($servings) && is_numeric($servings)) {
        $node->set('field_number_of_servings', $servings);
      }

      // Set difficulty level.
      if (!empty($difficulty)) {
        $node->set('field_difficulty', $difficulty);
      }

      // Set recipe categories.
      if (!empty($recipe_category) && is_array($recipe_category)) {
        $validated_categories = $this->validateTaxonomyTerms($recipe_category, 'recipe_category');
        if (!empty($validated_categories)) {
          $node->set('field_recipe_category', $validated_categories);
        }
      }

      // Set tags.
      if (!empty($tags) && is_array($tags)) {
        $validated_tags = $this->validateTaxonomyTerms($tags, 'tags');
        if (!empty($validated_tags)) {
          $node->set('field_tags', $validated_tags);
        }
      }

      // Set summary.
      if (!empty($summary)) {
        $node->set('field_summary', [
          'value' => $summary,
          'format' => 'basic_html',
        ]);
      }

      // Process and set ingredients.
      if (!empty($ingredients) && is_array($ingredients)) {
        $ingredients_list = $this->processIngredients($ingredients);
        if (!empty($ingredients_list)) {
          $node->set('field_ingredients', $ingredients_list);
        }
      }

      // Set cooking directions.
      if (!empty($directions)) {
        $node->set('field_recipe_instruction', [
          'value' => $directions,
          'format' => 'full_html',
        ]);
      }

      // Save the node.
      $node->save();

      $this->setOutput(sprintf(
        'Success: Recipe "%s" created successfully with ID: %d. Node URL: %s',
        $title,
        $node->id(),
        $node->toUrl('canonical', ['absolute' => TRUE])->toString()
      ));

    } catch (\Exception $e) {
      $this->setOutput('Error: Failed to create recipe node. ' . $e->getMessage());
      \Drupal::logger('ai_agents')->error('Recipe creation failed: @message', ['@message' => $e->getMessage()]);
    }
  }

  /**
   * Validates taxonomy term IDs.
   *
   * @param array $term_ids
   *   Array of term IDs to validate.
   * @param string $vocabulary
   *   The vocabulary machine name.
   *
   * @return array
   *   Array of validated term IDs.
   */
  protected function validateTaxonomyTerms(array $term_ids, string $vocabulary): array {
    $validated_terms = [];
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');

    foreach ($term_ids as $term_id) {
      if (!is_numeric($term_id)) {
        continue;
      }

      $term = $term_storage->load($term_id);
      if ($term && $term->bundle() === $vocabulary) {
        $validated_terms[] = ['target_id' => $term_id];
      }
      else {
        throw new \InvalidArgumentException("Invalid term ID $term_id for vocabulary $vocabulary.");
      }
    }

    return $validated_terms;
  }

  /**
   * Processes ingredients array into field-ready format.
   *
   * @param array $ingredients
   *   Array of ingredient objects with 'quantity' and 'item' properties.
   *
   * @return array
   *   Processed ingredients array for field storage.
   */
  protected function processIngredients(array $ingredients): array {
    $processed_ingredients = [];

    foreach ($ingredients as $ingredient) {
      if (is_array($ingredient) &&
          isset($ingredient['quantity']) &&
          isset($ingredient['item'])) {

        // Combine quantity and item into a single string.
        $ingredient_text = trim($ingredient['quantity'] . ' ' . $ingredient['item']);
        if (!empty($ingredient_text)) {
          $processed_ingredients[] = ['value' => $ingredient_text];
        }
      }
      elseif (is_string($ingredient) && !empty($ingredient)) {
        // Handle case where ingredient is just a string.
        $processed_ingredients[] = ['value' => $ingredient];
      }
    }

    return $processed_ingredients;
  }

}
