<?php

/**
 * -------------------------------------------------------------------------
 * GLPI Kyndryl Client Plugin
 * Copyright (C) 2025 by GLPI Kyndryl Client Plugin Team
 * -------------------------------------------------------------------------
 */

$LANG['kyndrylclient'] = [
  // General
  'Kyndryl Client' => 'Cliente Kyndryl',
  'Kyndryl Requester' => 'Requisitante Kyndryl',
  'Kyndryl Requesters' => 'Requisitantes Kyndryl',

  // Configuration
  'API Base URL' => 'URL Base da API',
  'Login' => 'Login',
  'Password' => 'Senha',
  'API User (GLPI)' => 'Usuário da API (GLPI)',
  'Request Source' => 'Origem da Requisição',
  'Group Rule' => 'Grupo Regra',
  'Test Connection' => 'Testar Conexão',
  'Configuration updated successfully' => 'Configuração atualizada com sucesso',

  // Validation
  'Base URL is required' => 'URL Base é obrigatória',
  'Invalid URL format' => 'Formato de URL inválido',
  'Login is required' => 'Login é obrigatório',
  'Password is required' => 'Senha é obrigatória',
  'API User is required' => 'Usuário da API é obrigatório',
  'Request Type is required' => 'Tipo de Requisição é obrigatório',
  'Group is required' => 'Grupo é obrigatório',

  // Ticket Types
  'Incident' => 'Incidente',
  'Service Request' => 'Requisição de Serviço',

  // Ticket Status
  'New' => 'Novo',
  'Processing (assigned)' => 'Em Andamento (atribuído)',
  'Processing (planned)' => 'Em Andamento (planejado)',
  'Pending' => 'Pendente',
  'Solved' => 'Solucionado',
  'Closed' => 'Fechado',

  // Messages
  'Ticket sent to Kyndryl' => 'Chamado enviado para Kyndryl',
  'Ticket sent to Kyndryl - RequesterID: %s' => 'Chamado enviado para Kyndryl - RequesterID: %s',
  'Ticket originated from Kyndryl - RequesterID: %s' => 'Chamado originado no Kyndryl - RequesterID: %s',
  'Ticket updated in Kyndryl' => 'Chamado atualizado no Kyndryl',
  'Followup added to Kyndryl' => 'Acompanhamento inserido no Kyndryl',

  // Status Messages
  'Solution provided in GLPI' => 'Solução providenciada no GLPI',
  'Ticket closed in GLPI' => 'Chamado encerrado no GLPI',
  'Worklog from GLPI' => 'Registro de trabalho do GLPI',

  // Errors
  'Kyndryl API Error (%s): %s' => 'Erro na API Kyndryl (%s): %s',
  'API request failed after %d attempts' => 'Requisição da API falhou após %d tentativas',
  'Invalid JSON response' => 'Resposta JSON inválida',
  'API request failed with status code: %d' => 'Requisição da API falhou com código: %d',
  'Missing required configuration: %s' => 'Configuração obrigatória ausente: %s',
  'Invalid configuration \'%s\': %s' => 'Configuração inválida \'%s\': %s',
  'Missing required dependency: %s' => 'Dependência obrigatória ausente: %s',

  // Operations
  'creation' => 'criação',
  'update' => 'atualização',
  'followup' => 'acompanhamento',
];
