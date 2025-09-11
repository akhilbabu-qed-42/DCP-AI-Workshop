<?php

namespace Drupal\dcp_ai_workshop\Hook;

use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai_agents\PluginInterfaces\AiAgentInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\dcp_ai_workshop\Plugin\AiFunctionCall\UpdateCookingInstructions;

/**
 * Hook implementations for the dcp_ai_workshop module.
 */
class DcpAiWorkshopHooks {

  /**
   * Implements hook_entity_presave().
   *
   * Adds a database entry when a recipe 'task' content type is saved.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being saved.
   */
  #[Hook('entity_presave')]
  public function entityPresave(EntityInterface $entity): void {
    die('here');
    // Check if the entity is a node and of type 'recipe'.
    if ($entity->getEntityTypeId() === 'node' && $entity->bundle() === 'task') {
      dump($entity->get('field_task_type')->value);die;
      $value = $entity->get('field_task_description')->getValue()[0]['value'];
      $provider = \Drupal::service('ai.provider')->getDefaultProviderForOperationType('chat');
      $provider_instance = \Drupal::service('ai.provider')->createInstance($provider['provider_id']);
      // Add a database entry.
      $agent = \Drupal::service('plugin.manager.ai_agents')->createInstance('recipe_generator');
      $agent->setChatInput(new ChatInput([
        new ChatMessage('user', strip_tags($value))
      ]));
      $agent->setAiProvider($provider_instance);
      $agent->setModelName($provider['model_id']);
      $solvability = $agent->determineSolvability();
      if ($solvability == AiAgentInterface::JOB_SOLVABLE) {
        \Drupal::messenger()->addMessage('Recipes have been created');
      }
      else {
        \Drupal::messenger()->addMessage('There was an unexpected error.', 'error');
      }
    }

    if ($entity->getEntityTypeId() === 'node' && $entity->bundle() === 'recipe') {
      $value = $entity->get('field_editor_feedback')->getValue()[0]['value'];
      $provider = \Drupal::service('ai.provider')->getDefaultProviderForOperationType('chat');
      $provider_instance = \Drupal::service('ai.provider')->createInstance($provider['provider_id']);
      // Add a database entry.
      $agent = \Drupal::service('plugin.manager.ai_agents')->createInstance('recipe_editor');
      $agent->setChatInput(new ChatInput([
        new ChatMessage('user', strip_tags($value))
      ]));
      $agent->setAiProvider($provider_instance);
      $agent->setModelName($provider['model_id']);
      $agent->setTokenContexts(['node' => $entity]);
      $solvability = $agent->determineSolvability();
      if ($solvability == AiAgentInterface::JOB_SOLVABLE) {
        $tool_results = $agent->getToolResults(TRUE);
        foreach ($tool_results as $tool) {
          if ($tool instanceof UpdateCookingInstructions) {
            $result = $tool->getReadableOutput();
            $result = \json_decode($result, TRUE);
            if (isset($result['cooking_instructions'])) {
              $entity->set('field_recipe_instruction', [
                'value' => $result['cooking_instructions'],
                'format' => 'full_html',
              ]);
            }
          }
        }
      }
      else {
        \Drupal::messenger()->addMessage('There was an unexpected error.', 'error');
      }
    }
  }

}
