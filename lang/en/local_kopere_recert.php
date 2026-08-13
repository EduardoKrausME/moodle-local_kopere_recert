<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * local_recertification.php
 *
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['activecycleexists'] = 'Este usuário já possui um ciclo de recertificação pendente, processando ou ativo neste curso.';
$string['activitycompletedat'] = 'Conclusão anterior';
$string['additionalcondition'] = 'Condição adicional {$a}';
$string['allcourses'] = 'Todos os cursos';
$string['automaticcyclename'] = 'Recertificação {$a}';
$string['automaticcyclereason'] = 'Recertificação automática conforme a política configurada no curso.';
$string['body'] = 'Mensagem';
$string['bulkkopere_recert'] = 'Recertificação em massa';
$string['bulkqueued'] = 'Os usuários selecionados foram enfileirados de forma independente.';
$string['bulkqueuedsummary'] = 'Processamento da fila concluído. Enfileirados: {$a->success}. Com falha ou ignorados: {$a->failed}.';
$string['certificatereferenceunavailable'] = 'O componente de certificado selecionado não está instalado ou não fornece a API de data de referência para recertificação.';
$string['cleanupbuilderhelp'] = 'As tabelas abaixo são descobertas no install.xml do componente. A tabela principal da atividade e tabelas sem userid/user_id não são oferecidas. A condição do usuário é sempre obrigatória.';
$string['cleanupconfigjson'] = 'Configuração da limpeza (JSON)';
$string['cleanupdata'] = 'Apagar/reiniciar dados';
$string['cleanupdefinition'] = 'Tabela de limpeza {$a}';
$string['cmid'] = 'CMID';
$string['completedat'] = 'Concluída';
$string['component'] = 'Componente';
$string['componentalreadyconfigured'] = 'Este componente já possui uma tarefa global.';
$string['componentnotinstalled'] = 'Componente não instalado';
$string['componentrepresentedbysubplugin'] = 'Este componente já é representado por um subplugin recerttask.';
$string['configuration'] = 'Configuração';
$string['configurednotices'] = 'Notificações configuradas';
$string['copyfiles'] = 'Copiar arquivos';
$string['courseconfiguration'] = 'Configuração da recertificação';
$string['courseidrequired'] = 'É necessário informar o curso para visualizar o histórico de outro usuário.';
$string['createhistory'] = 'Criar histórico';
$string['cycle'] = 'Ciclo';
$string['disabled'] = 'Desativado';
$string['edittask'] = 'Editar tarefa';
$string['enabled'] = 'Ativo';
$string['event_kopere_recert_completed'] = 'Recertificação concluída';
$string['event_kopere_recert_completed_description'] = 'O ciclo {$a->cycleid} foi concluído pelo usuário {$a->userid} no curso {$a->courseid}.';
$string['event_kopere_recert_created'] = 'Ciclo de recertificação criado';
$string['event_kopere_recert_created_description'] = 'O ciclo {$a->cycleid} foi criado para o usuário {$a->userid} no curso {$a->courseid}.';
$string['event_kopere_recert_failed'] = 'Falha na recertificação';
$string['event_kopere_recert_failed_description'] = 'O ciclo {$a->cycleid} falhou para o usuário {$a->userid} no curso {$a->courseid}.';
$string['event_kopere_recert_started'] = 'Recertificação iniciada';
$string['event_kopere_recert_started_description'] = 'O ciclo {$a->cycleid} foi iniciado para o usuário {$a->userid} no curso {$a->courseid}.';
$string['eventtype'] = 'Evento';
$string['execution_skipped_cycle_status'] = 'A execução foi ignorada porque o ciclo já está no status: {$a}.';
$string['executionplan'] = 'Plano de execução';
$string['filebuilderhelp'] = 'Regras genéricas de arquivos exigem itemid da File API = :userid. Se o componente relaciona arquivos ao usuário por outra tabela ou entidade, use um subplugin especializado.';
$string['fileconfigjson'] = 'Configuração de cópia de arquivos (JSON)';
$string['filedefinition'] = 'Origem de arquivos {$a}';
$string['filepath'] = 'Caminho do arquivo';
$string['filescopiedcount'] = '{$a} arquivos copiados.';
$string['filter'] = 'Filtrar';
$string['fixedday'] = 'Dia fixo';
$string['fixedmonth'] = 'Mês fixo';
$string['forumreplyhaschildren'] = 'Uma resposta do fórum não pode ser removida com segurança porque possui respostas filhas que precisam ser preservadas.';
$string['generic'] = 'Genérico';
$string['history'] = 'Histórico de recertificações';
$string['historyfiles'] = 'Arquivos históricos';
$string['historyrecordswouldbecreated'] = 'registros de histórico seriam criados.';
$string['historytemplate'] = 'Template Mustache do histórico';
$string['installedactivities'] = 'Atividades instaladas';
$string['intervaldays'] = 'Intervalo em dias';
$string['intervalmustbepositive'] = 'O intervalo deve ser maior que zero dias para este gatilho.';
$string['invalidcleanupconfig'] = 'Configuração de limpeza inválida.';
$string['invalidcycle'] = 'O ciclo selecionado não pertence a este curso.';
$string['invalidfileconfig'] = 'Configuração de arquivos inválida.';
$string['invalidjson'] = 'JSON inválido.';
$string['invalidselfreference'] = 'Tipo de referência inválido para recertificação manual.';
$string['kopere_recertlocked'] = 'Já existe uma recertificação deste usuário e curso em processamento.';
$string['kopere_recertpendingnav'] = 'Recertificação em andamento';
$string['kopere_recertqueued'] = 'A recertificação foi enfileirada para processamento isolado.';
$string['kopere_recertrequired'] = 'Recertificação obrigatória';
$string['kopere_recertstatusmessage'] = 'Sua certificação anterior não é mais considerada atual. Conclua novamente os requisitos do curso para finalizar esta recertificação.';
$string['kopereemailrecommendation'] = 'Para personalizar mais os emails de recertificação, você pode instalar opcionalmente o message_kopereemail.';
$string['logicalblockmessage'] = 'Sua certificação anterior não é mais considerada válida como certificação atual. Conclua novamente os requisitos do curso para finalizar esta recertificação.';
$string['missingcertificatereference'] = 'É necessário selecionar uma atividade de certificado como referência.';
$string['newtask'] = 'Nova tarefa';
$string['noeligiblecleanuptables'] = 'Este componente não possui tabela elegível para limpeza genérica.';
$string['nohistory'] = 'Nenhum histórico de recertificação foi encontrado.';
$string['nosimulationreport'] = 'O relatório da simulação não está mais disponível.';
$string['notconfigured'] = 'Não configurado';
$string['notice_available'] = 'Recertificação disponível';
$string['notice_completed'] = 'Recertificação concluída';
$string['notice_created'] = 'Recertificação criada';
$string['notice_due'] = 'Recertificação vencendo';
$string['notice_expired'] = 'Recertificação vencida';
$string['notice_started'] = 'Recertificação iniciada';
$string['notice_warning'] = 'Aviso de vencimento';
$string['notices'] = 'Notificações';
$string['notification_body_expiration_warning'] = 'A sua certificação atual do curso {{course.fullname}} está próxima da data de recertificação.';
$string['notification_body_kopere_recert_available'] = 'Uma nova recertificação do curso {{course.fullname}} está disponível.';
$string['notification_body_kopere_recert_completed'] = 'Sua recertificação do curso {{course.fullname}} foi concluída.';
$string['notification_body_kopere_recert_created'] = 'Um ciclo de recertificação foi criado para o curso {{course.fullname}}.';
$string['notification_body_kopere_recert_due'] = 'A recertificação do curso {{course.fullname}} chegou à data prevista.';
$string['notification_body_kopere_recert_expired'] = 'A sua certificação do curso {{course.fullname}} venceu e uma nova recertificação é obrigatória.';
$string['notification_body_kopere_recert_started'] = 'Sua recertificação do curso {{course.fullname}} começou. Conclua novamente as atividades obrigatórias.';
$string['notification_subject_expiration_warning'] = 'Aviso de vencimento da certificação: {$a}';
$string['notification_subject_kopere_recert_available'] = 'Recertificação disponível: {$a}';
$string['notification_subject_kopere_recert_completed'] = 'Recertificação concluída: {$a}';
$string['notification_subject_kopere_recert_created'] = 'Recertificação criada: {$a}';
$string['notification_subject_kopere_recert_due'] = 'Recertificação vencendo: {$a}';
$string['notification_subject_kopere_recert_expired'] = 'Certificação vencida: {$a}';
$string['notification_subject_kopere_recert_started'] = 'Recertificação iniciada: {$a}';
$string['offsetdays'] = 'Dias antes do vencimento';
$string['origin'] = 'Origem';
$string['pluginname'] = 'Recertificação';
$string['privacy:metadata:local_kopere_recert_cycle'] = 'Armazena os ciclos de recertificação.';
$string['privacy:metadata:local_kopere_recert_cycle:courseid'] = 'Curso que está sendo recertificado.';
$string['privacy:metadata:local_kopere_recert_cycle:createdby'] = 'Usuário que criou ou solicitou o ciclo.';
$string['privacy:metadata:local_kopere_recert_cycle:reason'] = 'Motivo da recertificação.';
$string['privacy:metadata:local_kopere_recert_cycle:userid'] = 'Usuário proprietário do ciclo.';
$string['privacy:metadata:local_kopere_recert_file'] = 'Armazena metadados dos arquivos copiados para o histórico.';
$string['privacy:metadata:local_kopere_recert_file:userid'] = 'Usuário proprietário do arquivo histórico copiado.';
$string['privacy:metadata:local_kopere_recert_history'] = 'Armazena snapshots históricos permanentes.';
$string['privacy:metadata:local_kopere_recert_history:datajson'] = 'Dados históricos estruturados fornecidos pela tarefa.';
$string['privacy:metadata:local_kopere_recert_history:html'] = 'Dados históricos renderizados por uma atividade ou tarefa de sistema.';
$string['privacy:metadata:local_kopere_recert_history:userid'] = 'Usuário proprietário do snapshot histórico.';
$string['privacy:metadata:local_kopere_recert_log'] = 'Armazena logs de execução da recertificação.';
$string['privacy:metadata:local_kopere_recert_log:message'] = 'Mensagem técnica da execução sem segredos.';
$string['privacy:metadata:local_kopere_recert_notice_log'] = 'Armazena o controle de notificações já enviadas.';
$string['privacy:metadata:local_kopere_recert_notice_log:userid'] = 'Usuário que recebeu a notificação.';
$string['reason'] = 'Motivo';
$string['reasonrequired'] = 'É obrigatório informar o motivo da recertificação.';
$string['recertifyselected'] = 'Recertificar selecionados';
$string['recordsaffectedcount'] = '{$a} registros afetados.';
$string['referencecmid'] = 'Atividade de certificado de referência';
$string['resetcompetencies'] = 'Reiniciar competências do curso';
$string['resetcompetencies_desc'] = 'Quando ativo, a tarefa de competências poderá reiniciar somente o estado das competências deste usuário neste curso. As definições das competências nunca são apagadas.';
$string['selfafterdays'] = 'Aluno pode solicitar após esta quantidade de dias da matrícula';
$string['selfafterdaysinvalid'] = 'A quantidade de dias para recertificação manual não pode ser negativa.';
$string['selfavailablein'] = 'Nova recertificação disponível em {$a} dias.';
$string['selfenabled'] = 'Permitir recertificação manual pelo aluno';
$string['selfkopere_recertnotavailable'] = 'Uma nova recertificação estará disponível em {$a}.';
$string['selfnotavailable'] = 'Uma nova recertificação ainda não está disponível.';
$string['selfreference_certificate'] = 'A partir da emissão do certificado selecionado';
$string['selfreference_completion'] = 'A partir da conclusão atual do curso';
$string['selfreference_enrolment'] = 'A partir da data da matrícula';
$string['selfreference_lastkopere_recert'] = 'A partir do último ciclo de recertificação concluído';
$string['selfreferencetype'] = 'Referência da recertificação manual';
$string['settings'] = 'Configurações';
$string['showkopereemailrecommendation'] = 'Mostrar recomendação do Kopere Email';
$string['showkopereemailrecommendation_desc'] = 'Quando message_kopereemail não estiver instalado, mostra uma recomendação discreta nas configurações do plugin.';
$string['simulate'] = 'Simular recertificação';
$string['simulation'] = 'Simulação de recertificação';
$string['simulationcompleted'] = 'SIMULAÇÃO CONCLUÍDA';
$string['simulationdetails'] = 'Detalhes antes das alterações';
$string['simulationrollback'] = 'Rollback controlado da simulação.';
$string['simulationrollbackdone'] = 'Rollback executado. Nenhuma informação da simulação foi gravada.';
$string['sourcecomponent'] = 'Componente de origem';
$string['sourcecontextid'] = 'ID do contexto de origem';
$string['sourcefilearea'] = 'Área de arquivos de origem';
$string['sourceitemid'] = 'ID do item de origem';
$string['sqlechomultiplecolumns'] = 'sqlecho deve retornar exatamente uma coluna.';
$string['sqlechomultiplerows'] = 'sqlecho deve retornar zero ou uma linha.';
$string['sqltemplatehelp'] = 'O Mustache pode usar {{#sqlecho}}...{{/sqlecho}} e {{#sqltable}}...{{/sqltable}}. O SQL é somente leitura e os valores devem usar os parâmetros :userid, :courseid, :cmid, :instanceid, :contextid, :cycleid ou :kopere_recertid.';
$string['startedat'] = 'Iniciada';
$string['startkopere_recert'] = 'Iniciar recertificação';
$string['structureddata'] = 'Dados estruturados do snapshot';
$string['subject'] = 'Assunto';
$string['subplugin'] = 'Subplugin';
$string['subpluginmissing'] = 'O subplugin configurado não está mais instalado';
$string['subplugintype_recerttask'] = 'Tarefa de recertificação';
$string['supportedplugins'] = 'Plugins suportados';
$string['systemcomponents'] = 'Componentes de sistema';
$string['table'] = 'Tabela';
$string['task_scan'] = 'Verificar recertificações e enfileirar usuários vencidos';
$string['tasks'] = 'Tarefas';
$string['trigger_certificate'] = 'Após a emissão do certificado';
$string['trigger_completion'] = 'Após a conclusão do curso';
$string['trigger_enrolment'] = 'Após a matrícula';
$string['trigger_fixeddate'] = 'Data fixa anual';
$string['trigger_lastkopere_recert'] = 'Após a última recertificação';
$string['triggertype'] = 'Gatilho da recertificação';
$string['type'] = 'Tipo';
$string['usercolumn'] = 'Coluna do usuário';
$string['usernotenrolled'] = 'O usuário selecionado não possui matrícula ativa neste curso.';
$string['viewhistory'] = 'Ver histórico de recertificações';
