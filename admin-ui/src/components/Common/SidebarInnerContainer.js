import React from 'react';

const SidebarInnerContainer = ({children, title}) => {
    return <div
        className={`bg-white border border-t-0 border-r-0 border-l-0
         border-b-card_border px-2.5 2xl:px-4 2xl:py-2.5 py-2 flex 
         flex-col  gap-y-1.5 xl:gap-y-2 2xl:gap-y-2.5`}>
        <p className={`text-dark text-xs 2xl:text-sm uppercase font-bold tracking-[0.04em]`}>
            {title}
        </p>
        {children}
    </div>
};

export default SidebarInnerContainer;