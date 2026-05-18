import * as React from "react"

import { cn } from "@/lib/utils"

function Input({ className, type, ...props }: React.ComponentProps<"input">) {
  return (
    <input
      type={type}
      data-slot="input"
      className={cn(
        "flex h-10 w-full rounded-xl border-2 border-emerald-600 dark:border-emerald-500 px-3 py-2 text-base placeholder:text-gray-600 dark:text-gray-400 ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 dark:focus-visible:ring-emerald-500 focus-visible:ring-offset-0 disabled:cursor-not-allowed disabled:opacity-50 md:text-md file:border-0 file:bg-[#f2f2f2] dark:file:bg-[#0d0d0d] file:text-md file:font-medium file:text-foreground",
        className
      )}
      {...props}
    />
  )
}

export { Input }
