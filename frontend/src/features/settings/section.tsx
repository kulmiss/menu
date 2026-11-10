export function SettingsSection({
  title,
  intro,
  children,
}: {
  title: string
  intro?: string
  children: React.ReactNode
}) {
  return (
    <section className='grid gap-4 border-b pb-8 last:border-0'>
      <div>
        <h2 className='text-lg font-medium'>{title}</h2>
        {intro && <p className='text-sm text-muted-foreground'>{intro}</p>}
      </div>
      {children}
    </section>
  )
}
