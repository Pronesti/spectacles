import type { CatalogChoice } from './domain'
export type KeyedChoices = { source: CatalogChoice[]; items: { id: string; choice: CatalogChoice }[]; nextId: number }

// Match surviving objects first so removing one of two equal manual names keeps
// the other tag's identity. Value matching also supports restored/cloned drafts.
export function keyCatalogChoices(previous: KeyedChoices | null, source: CatalogChoice[]): KeyedChoices {
  const available = new Set(previous?.items ?? [])
  const exact = source.map(choice => {
    const item = [...available].find(item => item.choice === choice)
    if (item) available.delete(item)
    return item
  })
  let nextId = previous?.nextId ?? 0
  const items = source.map((choice, index) => {
    const item = exact[index] ?? [...available].find(item => item.choice.catalogId === choice.catalogId && item.choice.name === choice.name)
    if (item) available.delete(item)
    return { id: item?.id ?? `choice-${nextId++}`, choice }
  })
  return { source, items, nextId }
}
