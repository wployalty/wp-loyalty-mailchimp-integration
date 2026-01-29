import React from 'react';

const ToggleSwitch = ({
                          isActive,
                          click,
                          deactivate_tooltip = "click to de-activate",
                          activate_tooltip = "click to activate",
                          isPro = true,

                      }) => {
    return <div className={`flex items-center  p-0.5 2xl:w-11 2xl:h-6 w-9 h-5 
    ${isPro ? "cursor-pointer" : "cursor-not-allowed"} transition delay-150 ease rounded-xl
    ${isActive && isPro ? "bg-blue_primary justify-end " : "bg-light_gray justify-start"}
  
    `}
                title={isActive ? deactivate_tooltip : activate_tooltip}
                onClick={click}
    >
        <span className={` 2xl:h-5 h-4 2xl:w-5 w-4 rounded-full
         bg-white
         `}
        />
    </div>
};

export default ToggleSwitch;