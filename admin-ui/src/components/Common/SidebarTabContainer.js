import React from 'react';
import {UiLabelContext} from "../../Context";

const SidebarTabContainer = ({label, tabIcon, click, isPro = true,}) => {
    const labels = React.useContext(UiLabelContext);

    return <div onClick={click}
                className={`flex  ${isPro ? "cursor-pointer" : "cursor-not-allowed"} gap-x-2  items-center justify-between w-full text-light px-2.5 2xl:px-4 py-3 2xl:py-4  border border-t-0 border-r-0 border-l-0  border-b-card_border`}
    >
        <div className={`flex items-center gap-x-2 `}>
            <i className={`wlr wlrf-${tabIcon} bg text-md font-medium color-important `}/>
            <p className={`text-md font-medium`}>{label}</p>
        </div>
        {!isPro ? <div className={`flex items-center  cursor-pointer   justify-center `}
            >
            <span className="bg-blue_primary text-white font-medium rounded text-xs px-1.5 py-1"
                  onClick={(e) => {
                      e.preventDefault();
                      window.open(labels.common.buy_pro_url);
                  }}
            >
                {labels.common.upgrade_text}</span>
            </div> :
            <i className={`wlr wlrf-arrow_right text-md font-medium color-important `}/>}

    </div>
};

export default SidebarTabContainer;