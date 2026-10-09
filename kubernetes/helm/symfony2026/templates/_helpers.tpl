{{- define "symfony2026.generatedConfigMaps" -}}
{{- range $part := splitList "\n---\n" .Values.generatedConfigMaps }}
{{- $config := fromYaml $part }}
{{- if eq $config.kind "ConfigMap" }}
{{- if ne $config.metadata.namespace $.Release.Namespace }}{{ fail (printf "Generated ConfigMap %s belongs to namespace %s; expected %s" $config.metadata.name $config.metadata.namespace $.Release.Namespace) }}{{ end }}
{{- $_ := set $config.metadata "annotations" (merge (dict "helm.sh/resource-policy" "keep") (default dict $config.metadata.annotations)) }}
---
{{ toYaml $config }}
{{- end }}
{{- end }}
{{- end -}}
